<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\ClassModel;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\StudentProfile;
use App\Models\TimetableSlot;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

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
    /**
     * One class, one day, broken down by period and by student.
     *
     * This is the register sheet the office asks for when a parent telephones:
     * "was my son in school on Tuesday, and which periods did he miss". It is
     * shaped around the timetable rather than around the marks, because a day at
     * this college is a list of periods and a student attends or misses each one
     * separately - an overall percentage for the day would hide exactly the
     * detail the caller opened the page for.
     *
     * The period list comes from the timetable, not from the attendance rows, so
     * an unmarked period still appears as a period with nothing in it. A day whose
     * grid was never filled in comes back with no periods at all, which the page
     * reports as such rather than as an attendance of zero.
     *
     * @return array<string, mixed>
     */
    public function dailyReport(int $classId, string $date): array
    {
        $class = ClassModel::with(['stream', 'academicSession'])->find($classId);

        if ($class === null) {
            return [
                'class' => null,
                'date' => $date,
                'periods' => [],
                'students' => [],
                'overall' => $this->withPercentage($this->emptyTotals()),
            ];
        }

        $slots = TimetableSlot::query()
            ->where('class_id', $classId)
            ->where('day_of_week', Carbon::parse($date)->dayOfWeekIso)
            ->whereHas('period', fn (Builder $query) => $query->where('is_break', false))
            ->with(['period', 'classSubject.subject', 'classSubject.teacher'])
            ->get()
            // Sorted in PHP on the handful of slots a day holds, rather than with a
            // join to order by the related period's number.
            ->sortBy(fn (TimetableSlot $slot) => $slot->period?->number ?? 0)
            ->values();

        /*
            One query for the whole day, grouped by period, rather than a count
            per period. Six periods would otherwise cost six round trips before
            any row is written.
        */
        $perPeriod = Attendance::query()
            ->where('attendance_date', $date)
            ->whereIn(
                'class_subject_id',
                $slots->pluck('class_subject_id')->unique(),
            )
            ->selectRaw(
                'class_subject_id, period_id,'
                .$this->perStatusCountsSql()
            )
            ->groupBy('class_subject_id', 'period_id')
            ->get()
            ->keyBy(fn (object $row): string => $row->class_subject_id.'-'.$row->period_id);

        $periods = $slots->map(function (TimetableSlot $slot) use ($perPeriod): array {
            $row = $perPeriod->get($slot->class_subject_id.'-'.$slot->period_id);
            $totals = $this->rowTotals($row);

            return [
                'period_id' => $slot->period_id,
                'period_number' => $slot->period?->number,
                'period_label' => $slot->period?->label,
                'start_time' => $slot->period?->start_time?->format('H:i'),
                'end_time' => $slot->period?->end_time?->format('H:i'),
                'class_subject_id' => $slot->class_subject_id,
                'subject_name' => $slot->classSubject?->subject?->name ?? 'Subject',
                'subject_code' => $slot->classSubject?->subject?->code,
                'teacher_name' => $slot->classSubject?->teacher?->name ?? 'Unassigned',
                ...$totals,

                'percentage' => $this->percentageFrom($totals),

                // False when the teacher has not submitted the register at all,
                // which is different from a submitted register of all absences.
                'is_marked' => $totals['total'] > 0,
            ];
        })->values()->all();

        $students = $this->dailyStudents($class, $date, $periods);

        return [
            'class' => [
                'id' => $class->id,
                'display_name' => $class->displayName(),
                'grade_level' => $class->grade_level,
                'section' => $class->section,
                'stream_name' => $class->stream?->name ?? 'Unknown stream',
                'session_name' => $class->academicSession?->name ?? '—',
            ],
            'date' => $date,
            'periods' => $periods,
            'students' => $students,
            'overall' => $this->withPercentage($this->sumTotals($students)),
        ];
    }

    /**
     * The per-status count expressions every aggregate query shares.
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

    /**
     * The roll for a daily report, with each student's status for each period.
     *
     * The status rows are pulled once and keyed by student and period, then each
     * student's grid is assembled in PHP. Fetching them per student would be a
     * query per row on a page that is read in full every time it is opened.
     *
     * @param  array<int, array<string, mixed>>  $periods
     * @return array<int, array<string, mixed>>
     */
    private function dailyStudents(
        ClassModel $class,
        string $date,
        array $periods,
    ): array {
        $enrollments = Enrollment::query()
            ->where('class_id', $class->id)
            ->where('academic_session_id', $class->academic_session_id)
            ->where('status', 'active')
            ->with('studentProfile.user')
            ->get();

        $marks = Attendance::query()
            ->where('attendance_date', $date)
            ->whereIn(
                'class_subject_id',
                array_column($periods, 'class_subject_id'),
            )
            ->get(['student_profile_id', 'class_subject_id', 'period_id', 'status'])
            ->keyBy(fn (object $row): string => $row->student_profile_id.'-'.$row->period_id);

        return $enrollments
            ->map(function (Enrollment $enrollment) use ($marks, $periods): array {
                $student = $enrollment->studentProfile;
                $totals = $this->emptyTotals();

                $grid = array_map(function (array $period) use ($marks, $enrollment, &$totals): array {
                    $mark = $marks->get($enrollment->student_profile_id.'-'.$period['period_id']);
                    $status = $mark?->status;

                    if ($status !== null) {
                        $totals['total']++;

                        // Only a real status is counted. A value the CHECK
                        // constraint allows but this array does not name would
                        // otherwise inflate the total without matching any count.
                        if (array_key_exists($status, $totals)) {
                            $totals[$status]++;
                        }
                    }

                    return [
                        'period_id' => $period['period_id'],
                        'status' => $status,
                        'subject_name' => $period['subject_name'],
                    ];
                }, $periods);

                return [
                    'student_profile_id' => $enrollment->student_profile_id,
                    'roll_number' => (string) ($student?->roll_number ?? ''),
                    'student_name' => (string) ($student?->user?->name ?? 'Unknown Student'),
                    'periods' => $grid,
                    ...$totals,
                    'percentage' => $this->percentageFrom($totals),
                ];
            })
            ->sortBy('roll_number')
            ->values()
            ->all();
    }

    /**
     * Five zeroes, for a period or student that has nothing recorded yet.
     *
     * @return array{total: int, present: int, absent: int, late: int, leave: int}
     */
    private function emptyTotals(): array
    {
        return ['total' => 0, 'present' => 0, 'absent' => 0, 'late' => 0, 'leave' => 0];
    }

    /**
     * Sum a set of per-row count arrays into one.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{total: int, present: int, absent: int, late: int, leave: int}
     */
    private function sumTotals(array $rows): array
    {
        $sum = $this->emptyTotals();

        foreach ($rows as $row) {
            foreach (array_keys($sum) as $key) {
                $sum[$key] += (int) ($row[$key] ?? 0);
            }
        }

        return $sum;
    }

    /**
     * Attach the percentage that follows from a set of counts.
     *
     * Null when the counts are all zero, so an unmarked period or student reads
     * as unknown rather than as an attendance of nought percent.
     *
     * @param  array{total: int, present: int, absent: int, late: int, leave: int}  $totals
     * @return array<string, mixed>
     */
    private function withPercentage(array $totals): array
    {
        return [
            ...$totals,
            'percentage' => $this->percentageFrom($totals),
        ];
    }

    /**
     * One class across a date range, per student and per subject.
     *
     * The question this answers is "how has this class done this term", which is
     * different from the daily report in that it aggregates over weeks: the
     * per-student figure is what a parent disputes and the per-subject figure is
     * what tells the office whether one teacher's register is the problem.
     *
     * Two grouped queries serve the whole page - one by student, one by subject -
     * rather than one per row. The marks are never loaded individually, because
     * a month of a full class is thousands of rows and the page only needs the
     * counts.
     *
     * @return array<string, mixed>
     */
    public function rangeReport(int $classId, string $from, string $to): array
    {
        $class = ClassModel::with('stream')->find($classId);

        if ($class === null) {
            return [
                'class' => null,
                'from' => $from,
                'to' => $to,
                'days_count' => 0,
                'students' => [],
                'by_subject' => [],
                'overall' => $this->withPercentage($this->emptyTotals()),
            ];
        }

        $classSubjectIds = ClassSubject::query()
            ->where('class_id', $classId)
            ->pluck('id');

        $base = fn (): Builder => Attendance::query()
            ->whereIn('class_subject_id', $classSubjectIds)
            ->whereBetween('attendance_date', [$from, $to]);

        $enrollments = Enrollment::query()
            ->where('class_id', $classId)
            ->where('academic_session_id', $class->academic_session_id)
            ->where('status', 'active')
            ->with('studentProfile.user')
            ->get();

        $perStudent = $base()
            ->selectRaw('student_profile_id')
            ->selectRaw($this->perStatusCountsSql())
            ->groupBy('student_profile_id')
            ->get()
            ->keyBy('student_profile_id');

        // Every enrolled student appears, marked or not: a class where two
        // registers were never submitted is a fact the office needs to see.
        $students = $enrollments
            ->map(function (Enrollment $enrollment) use ($perStudent): array {
                $student = $enrollment->studentProfile;
                $totals = $this->rowTotals($perStudent->get($enrollment->student_profile_id));
                $percentage = $this->percentageFrom($totals);

                return [
                    'student_profile_id' => $enrollment->student_profile_id,
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

        /*
            getQuery() rather than get(): the grouped rows carry subject_name and
            teacher_name, which the Attendance model has never heard of, so
            hydrating one would hand back an object whose joined attributes are
            all null and a subject column of blank cells.
        */
        $bySubject = $base()
            ->join('class_subjects', 'class_subjects.id', '=', 'attendances.class_subject_id')
            ->join('subjects', 'subjects.id', '=', 'class_subjects.subject_id')
            ->join('users', 'users.id', '=', 'class_subjects.teacher_id')
            // One selectRaw, because a second call appends its own comma and
            // leaves a stray one between the two fragments.
            ->selectRaw(
                'class_subjects.id as class_subject_id,
             subjects.name as subject_name,
             subjects.code as subject_code,
             users.name as teacher_name,'
                .$this->perStatusCountsSql()
            )
            ->groupBy(
                'class_subjects.id',
                'subjects.name',
                'subjects.code',
                'users.name',
            )
            ->getQuery()
            ->get()
            ->map(function (object $row): array {
                $totals = $this->rowTotals($row);

                return [
                    'class_subject_id' => (int) $row->class_subject_id,
                    'subject_name' => (string) $row->subject_name,
                    'subject_code' => $row->subject_code,
                    'teacher_name' => (string) $row->teacher_name,
                    ...$totals,
                    'percentage' => $this->percentageFrom($totals),
                ];
            })
            ->sortBy('subject_name')
            ->values()
            ->all();

        return [
            'class' => [
                'id' => $class->id,
                'display_name' => $class->displayName(),
                'grade_level' => $class->grade_level,
                'section' => $class->section,
                'stream_name' => $class->stream?->name ?? 'Unknown stream',
            ],
            'from' => $from,
            'to' => $to,

            // School days rather than calendar days: a month-end range usually
            // covers far fewer teaching days than its name suggests, and the card
            // is there to stop that being misread as thin data.
            'days_count' => $this->schoolDaysBetween($from, $to),
            'students' => $students,
            'by_subject' => $bySubject,
            'overall' => $this->withPercentage($this->sumTotals($students)),
        ];
    }

    /**
     * How many Monday-to-Saturday days a range covers.
     *
     * Sundays are excluded because nothing is taught on one. A date before the
     * start of the range returns zero rather than a negative number.
     */
    private function schoolDaysBetween(string $from, string $to): int
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        if ($end->lessThan($start)) {
            return 0;
        }

        $days = 0;
        $cursor = $start->copy();

        while ($cursor->lessThanOrEqualTo($end)) {
            if ($cursor->dayOfWeek !== CarbonInterface::SUNDAY) {
                $days++;
            }

            $cursor->addDay();
        }

        return $days;
    }
}
