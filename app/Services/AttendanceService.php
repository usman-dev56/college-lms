<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\ClassModel;
use App\Models\Enrollment;
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
     *
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
