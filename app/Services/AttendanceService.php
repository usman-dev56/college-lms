<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Builder;

/**
 * Attendance percentages, computed live from the register.
 *
 * Read-only by design. Nothing here writes, and nothing is cached: the table
 * is indexed for exactly these aggregates and the volume is a college roll
 * times a term of periods, so a grouped query is cheaper than keeping a
 * summary row in step with every mark a teacher saves.
 *
 * Two rules run through every method here. Only 'absent' reduces a
 * percentage - late arrival and approved leave both count as attending,
 * because the college sanctioned them. And a student with no records at all
 * has no percentage: null, never zero. "0%" would tell a parent their child
 * failed to attend anything, when the truth is that nobody has marked
 * anything yet.
 */
class AttendanceService
{
    /**
     * The board eligibility threshold, as a percentage.
     *
     * A percentage below this risks being barred from sitting the board
     * exams, which is why the flag is on every row rather than only on the
     * overall figure.
     */
    public const THRESHOLD = 75.0;

    /**
     * One student's attendance percentage, overall or for one subject.
     *
     * Null when there are no records, which is different from 0 and must be
     * rendered differently.
     */
    public function percentageForStudent(
        int $studentProfileId,
        ?int $classSubjectId = null,
    ): ?float {
        $totals = $this->countsFor(
            Attendance::query()
                ->where('student_profile_id', $studentProfileId)
                ->when(
                    $classSubjectId,
                    fn (Builder $query) => $query->where(
                        'class_subject_id',
                        $classSubjectId,
                    ),
                ),
        );

        return $this->percentageFrom($totals);
    }

    /**
     * A student's overall percentage plus a per-subject breakdown.
     *
     * The overall figure is a second query rather than a sum of the subject
     * rows: a student with no records for some subject still needs an overall
     * number, and deriving it from the grouped rows would silently disagree
     * with percentageForStudent() the moment a row were filtered.
     *
     * @return array{overall: array<string, mixed>, by_subject: array<int, array<string, mixed>>}
     */
    public function summaryForStudent(int $studentProfileId): array
    {
        $overall = $this->countsFor(
            Attendance::query()
                ->where('student_profile_id', $studentProfileId),
        );

        /*
            One grouped query joined to the three tables that carry the names.
            Joined rather than eager loaded because the aggregation is what
            needs the rows - loading the relations afterwards would mean a
            second query per class-subject just to look up a name already in
            the join.
        */
        $bySubject = Attendance::query()
            ->join('class_subjects', 'class_subjects.id', '=', 'attendances.class_subject_id')
            ->join('subjects', 'subjects.id', '=', 'class_subjects.subject_id')
            ->join('classes', 'classes.id', '=', 'class_subjects.class_id')
            ->join('streams', 'streams.id', '=', 'classes.stream_id')
            ->where('attendances.student_profile_id', $studentProfileId)
            ->groupBy(
                'attendances.class_subject_id',
                'subjects.name',
                'subjects.code',
                'classes.grade_level',
                'classes.section',
                'streams.name',
            )
            ->selectRaw(
                'attendances.class_subject_id,
                 subjects.name as subject_name,
                 subjects.code as subject_code,
                 classes.grade_level,
                 classes.section,
                 streams.name as stream_name,
                '.$this->perStatusCountsSql(),
            )
            /*
                getQuery(), not get(). A joined aggregate is not an
                Attendance model - the columns are subject_name and
                grade_level, which that model has never heard of - so
                hydrating it would hand back an object whose joined
                attributes are all null, and every subject would come back
                unnamed. The base query returns the plain row the aggregate
                actually produced.
            */
            ->getQuery()
            ->get()
            ->map(function (object $row): array {
                $totals = $this->rowTotals($row);

                $percentage = $this->percentageFrom($totals);

                return [
                    'class_subject_id' => (int) $row->class_subject_id,
                    'subject_name' => (string) $row->subject_name,
                    'subject_code' => $row->subject_code,
                    'class_display_name' => $this->classDisplayName($row),
                    ...$totals,
                    'percentage' => $percentage,
                    'is_below_threshold' => $this->isBelowThreshold($percentage),
                ];
            })
            // Alphabetical by subject, as the page contract promises. Sorted
            // in PHP on the handful of rows a student has rather than in SQL,
            // so the ordering uses the same subject_name the row displays.
            ->sortBy('subject_name')
            ->values()
            ->all();

        $overallPercentage = $this->percentageFrom($overall);

        return [
            'overall' => [
                ...$overall,
                'percentage' => $overallPercentage,
                'is_below_threshold' => $this->isBelowThreshold($overallPercentage),
            ],
            'by_subject' => $bySubject,
        ];
    }

    /**
     * Every actively enrolled student's attendance in one class.
     *
     * Scoped to the class's own session, and every enrolled student appears
     * even with no marks: a register that silently omitted the students who
     * were never marked would read as a class where they do not exist.
     *
     * One grouped query for the whole class, then joined to the roll in PHP.
     * Calling summaryForStudent() per student would run two queries each,
     * which for a full class is two dozen round trips on a page that has to
     * render instantly.
     *
     * @return array<int, array<string, mixed>>
     */
    public function summaryForClass(int $classId): array
    {
        $class = ClassModel::with('academicSession')->find($classId);

        if ($class === null) {
            return [];
        }

        $enrollments = Enrollment::query()
            ->where('class_id', $classId)
            ->where('academic_session_id', $class->academic_session_id)
            ->where('status', 'active')
            ->with('studentProfile.user')
            ->get();

        $counts = Attendance::query()
            ->join('class_subjects', 'class_subjects.id', '=', 'attendances.class_subject_id')
            ->where('class_subjects.class_id', $classId)
            ->groupBy('attendances.student_profile_id')
            // The grouped column has to be selected as well: keyBy() reads it
            // off the row, and a row that does not carry it collapses every
            // student in the class onto one empty key.
            ->selectRaw('attendances.student_profile_id, '.$this->perStatusCountsSql())
            // getQuery() for the same reason as summaryForStudent(): the row
            // is an aggregate, not an Attendance model.
            ->getQuery()
            ->get()
            ->keyBy('student_profile_id');

        return $enrollments
            ->map(function (Enrollment $enrollment) use ($counts): array {
                $student = $enrollment->studentProfile;
                $totals = $this->rowTotals(
                    $counts->get($enrollment->student_profile_id),
                );

                $percentage = $this->percentageFrom($totals);

                return [
                    'student_profile_id' => (int) $enrollment->student_profile_id,
                    'roll_number' => (string) ($student?->roll_number ?? ''),
                    'student_name' => (string) ($student?->user?->name ?? 'Unknown Student'),
                    ...$totals,
                    'percentage' => $percentage,
                    'is_below_threshold' => $this->isBelowThreshold($percentage),
                ];
            })
            ->sortBy('roll_number')
            ->values()
            ->all();
    }

    /**
     * The per-status count expressions every aggregate query shares.
     *
     * Written once so the four counts cannot drift apart between the three
     * methods.
     *
     * SUM(CASE ...) rather than COUNT(*) FILTER because the FILTER form needs
     * a bound parameter for each status, and the SoftDeletes global scope
     * contributes a positional binding of its own - PDO rejects a statement
     * that mixes named and positional parameters. Inlining the statuses here
     * keeps the whole statement free of the status bindings; the names are
     * interpolated from the model's own constants, so there is still only one
     * place a status is spelled out, and they are not user input.
/**
     * Every active student whose attendance falls below the threshold.
     *
     * This is the list the office works from before the board result: a
     * student under the mark risks being barred from sitting the exams, and
     * somebody has to telephone their parents. It is deliberately the whole
     * roll rather than one class, because a defaulter is a fact about a
     * student and does not stop at the edge of their class.
     *
     * A student with no attendance records at all is NOT a defaulter. They
     * have no percentage rather than a low one, and listing them as though
     * they had missed everything would name a parent over a problem that does
     * not exist yet.
     *
     * Read-only: every percentage comes from summaryForStudent(), so the list
     * here and the number on a student's own page cannot disagree.
     *
     * @param  array{batch_id?: int|null, class_id?: int|null, stream_id?: int|null}  $filters
     * @return array{students: array<int, array<string, mixed>>, by_class: array<int, array<string, mixed>>, by_batch: array<int, array<string, mixed>>, total: int}
     */
    public function defaulters(array $filters = []): array
    {
        $students = StudentProfile::query()
            ->where('status', 'active')
            ->whereHas('user', fn (Builder $query) => $query->where('is_active', true))
            ->when(
                $filters['batch_id'] ?? null,
                fn (Builder $query, int $batchId) => $query->where('batch_id', $batchId),
            )
            ->with(['user', 'batch'])
            ->get();

        $rows = [];

        foreach ($students as $student) {
            $summary = $this->summaryForStudent($student->id);

            $percentage = $summary['overall']['percentage'];

            // Null is skipped on purpose: nothing marked is unknown, not low.
            if ($percentage === null || ! $this->isBelowThreshold($percentage)) {
                continue;
            }

            $enrollment = $student->currentEnrollment();
            $class = $enrollment?->classModel;

            if (isset($filters['class_id']) && $filters['class_id'] !== null) {
                if ((int) ($class?->id ?? 0) !== (int) $filters['class_id']) {
                    continue;
                }
            }

            if (isset($filters['stream_id']) && $filters['stream_id'] !== null) {
                if ((int) ($class?->stream_id ?? 0) !== (int) $filters['stream_id']) {
                    continue;
                }
            }

            $rows[] = [
                'student_profile_id' => $student->id,
                'roll_number' => (string) ($student->roll_number ?? ''),
                'student_name' => (string) ($student->user?->name ?? 'Unknown Student'),
                'batch_id' => (int) $student->batch_id,
                'batch_name' => (string) ($student->batch?->name ?? ''),

                // Null when the student has no live enrollment: they are on
                // the roll but not in a class, which is a real state and the
                // page has to be able to say "not enrolled" rather than blank.
                'class_id' => $class?->id !== null ? (int) $class->id : null,
                'class_display_name' => $class?->displayName(),

                'percentage' => $percentage,

                // How far below the mark the student is, which is what a
                // phone call is actually about: not "70%", but "5% short".
                'shortfall' => round(self::THRESHOLD - $percentage, 2),

                'present' => $summary['overall']['present'],
                'absent' => $summary['overall']['absent'],
                'late' => $summary['overall']['late'],
                'leave' => $summary['overall']['leave'],
                'total' => $summary['overall']['total'],
            ];
        }

        // Worst first. The list exists to be worked down, and the students
        // furthest from the mark are the ones who have to be called first.
        usort(
            $rows,
            fn (array $a, array $b): int => $a['percentage'] <=> $b['percentage'],
        );

        return [
            'students' => $rows,
            'by_class' => $this->groupCounts($rows, 'class_id', 'class_display_name'),
            'by_batch' => $this->groupCounts($rows, 'batch_id', 'batch_name'),
            'total' => count($rows),
        ];
    }

    /**
     * Count the rows in a group and name each group.
     *
     * A student with no class is grouped under null, which the page renders
     * as "Not enrolled" rather than dropping: they are still defaulters and
     * still need to be contacted.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function groupCounts(array $rows, string $key, string $labelKey): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $id = $row[$key];

            if (! isset($groups[$id])) {
                $groups[$id] = [
                    $key => $id,
                    $labelKey => $row[$labelKey],
                    'defaulter_count' => 0,
                ];
            }

            $groups[$id]['defaulter_count']++;
        }

        return array_values($groups);
    }

    /**
     * COALESCE guards the empty group: with no rows SUM is null, and a null
     * cast to int would read as a gap rather than a zero.
     */
    private function perStatusCountsSql(): string
    {
        $status = fn (string $constant): string => "'".$constant."'";

        return sprintf(
            '
            COUNT(*) as total,
            COALESCE(SUM(CASE WHEN status = %1$s THEN 1 ELSE 0 END), 0) as present,
            COALESCE(SUM(CASE WHEN status = %2$s THEN 1 ELSE 0 END), 0) as absent,
            COALESCE(SUM(CASE WHEN status = %3$s THEN 1 ELSE 0 END), 0) as late,
            COALESCE(SUM(CASE WHEN status = %4$s THEN 1 ELSE 0 END), 0) as leave_
        ',
            $status(Attendance::STATUS_PRESENT),
            $status(Attendance::STATUS_ABSENT),
            $status(Attendance::STATUS_LATE),
            $status(Attendance::STATUS_LEAVE),
        );
    }

    /**
     * The per-status counts for one grouped aggregate.
     *
     * @return array{present: int, absent: int, late: int, leave: int, total: int}
     */
    private function countsFor(Builder $query): array
    {
        return $this->rowTotals(
            $query
                ->selectRaw($this->perStatusCountsSql())
                ->first(),
        );
    }

    /**
     * One aggregated row read into the counts array.
     *
     * Accepts null as well as a row, because a student on the roll with
     * nothing marked has no row in the aggregate at all and must read as five
     * zeroes rather than as an error.
     *
     * @return array{present: int, absent: int, late: int, leave: int, total: int}
     */
    private function rowTotals(?object $row): array
    {
        return [
            'present' => (int) ($row->present ?? 0),
            'absent' => (int) ($row->absent ?? 0),
            'late' => (int) ($row->late ?? 0),
            'leave' => (int) ($row->leave_ ?? 0),
            'total' => (int) ($row->total ?? 0),
        ];
    }

    /**
     * The attendance percentage, or null when nothing has been marked.
     *
     * Present, late and leave all count as attending; only absent is against
     * the student. Rounded to two decimals so a column of percentages lines
     * up instead of showing a different number of digits per row.
     *
     * @param  array{present: int, absent: int, late: int, leave: int, total: int}  $totals
     */
    private function percentageFrom(array $totals): ?float
    {
        if ($totals['total'] === 0) {
            return null;
        }

        $attended = $totals['present'] + $totals['late'] + $totals['leave'];

        return round($attended / $totals['total'] * 100, 2);
    }

    /**
     * Whether this percentage would fail the board eligibility threshold.
     *
     * Null is never below: a student with nothing marked is unknown, not
     * failing, and flagging them would put an at-risk badge on every student
     * who simply has not been marked yet.
     */
    private function isBelowThreshold(?float $percentage): bool
    {
        return $percentage !== null && $percentage < self::THRESHOLD;
    }

    /**
     * "11th Pre-Medical A", built from the joined row.
     *
     * Built here rather than through ClassModel::displayName() because that
     * method needs the model loaded with its relations, and this aggregate
     * deliberately avoids a query per row just to format a name.
     */
    private function classDisplayName(object $row): string
    {
        $ordinal = match ((int) $row->grade_level) {
            11 => '11th',
            12 => '12th',
            default => (string) $row->grade_level,
        };

        return trim("{$ordinal} {$row->stream_name} {$row->section}");
    }
}
