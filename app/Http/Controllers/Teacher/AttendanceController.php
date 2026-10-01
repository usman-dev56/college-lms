<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\MarkAttendanceRequest;
use App\Models\Attendance;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\Period;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    /**
     * The school week, 1 = Monday .. 6 = Saturday.
     *
     * day_of_week on a timetable slot is stored as exactly these numbers, so
     * this list is the single source of truth for the index and the heading.
     *
     * @var array<int, string>
     */
    private const DAYS = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    /**
     * Today's periods for the signed-in teacher.
     *
     * Built from timetable slots rather than from class-subjects, because the
     * question a teacher has at 09:00 is "what am I teaching now", and a
     * class-subject row only says what they teach over a term.
     *
     * A Sunday is not a normal empty result: the grid has no row 7, so the
     * lookup finds nothing and would otherwise render as "you have no periods
     * scheduled", which reads as a mistake. Sunday is passed to the page so it
     * can say what actually happened.
     */
    public function index(Request $request): Response
    {
        $teacher = $request->user();

        abort_unless($teacher?->isTeacher(), 403);

        $today = Carbon::today();
        $dayOfWeek = $today->dayOfWeekIso;
        $todayName = self::DAYS[$dayOfWeek] ?? 'Sunday';

        $slots = $this->todaysSlots($teacher, $dayOfWeek);

        $marked = $this->markedCounts($slots, $today);
        $enrolled = $this->enrolledCounts($slots);

        return Inertia::render('Teacher/Attendance/Index', [
            'today_name' => $todayName,
            'attendance_date' => $today->toDateString(),
            'teacher_name' => $teacher->name,
            'is_sunday' => ! isset(self::DAYS[$dayOfWeek]),

            'slots' => $slots->map(function (TimetableSlot $slot) use ($marked, $enrolled): array {
                $class = $slot->classSubject?->classModel;
                $key = $slot->class_subject_id.'-'.$slot->period_id;
                $count = (int) ($marked[$key] ?? 0);

                return [
                    'slot_id' => $slot->id,
                    'class_subject_id' => $slot->class_subject_id,
                    'period' => [
                        'id' => $slot->period?->id,
                        'number' => $slot->period?->number,
                        'label' => $slot->period?->label,
                        'start_time' => $slot->period?->start_time?->format('H:i'),
                        'end_time' => $slot->period?->end_time?->format('H:i'),
                        'is_break' => (bool) $slot->period?->is_break,
                    ],
                    'class' => [
                        'id' => $class?->id,
                        'display_name' => $class?->displayName(),
                    ],
                    'subject' => [
                        'id' => $slot->classSubject?->subject?->id,
                        'name' => $slot->classSubject?->subject?->name,
                        'code' => $slot->classSubject?->subject?->code,
                    ],

                    // Marked is decided by a count rather than by one row's
                    // existence, so a half-saved period reads as unmarked -
                    // which is what it is.
                    'is_marked' => $count > 0,
                    'marked_count' => $count,
                    'enrolled_count' => (int) ($enrolled[$class?->id] ?? 0),
                ];
            })->values()->all(),
        ]);
    }

    /**
     * The teacher's timetable slots for one day, in teaching order.
     *
     * Breaks are dropped rather than shown as unmarkable rows: the timetable
     * builder already refuses to schedule a subject into one, so a slot here
     * would only appear if the grid were edited underneath this page.
     *
     * @return Collection<int, TimetableSlot>
     */
    private function todaysSlots(User $teacher, int $dayOfWeek): Collection
    {
        if (! isset(self::DAYS[$dayOfWeek])) {
            return collect();
        }

        return TimetableSlot::query()
            ->where('day_of_week', $dayOfWeek)
            ->whereHas(
                'classSubject',
                fn (Builder $query) => $query->where('teacher_id', $teacher->id),
            )
            ->whereHas('period', fn (Builder $query) => $query->where('is_break', false))
            ->with([
                'period',
                'classSubject.subject',
                'classSubject.classModel.stream',
            ])
            ->get()
            // Sorted in PHP rather than in SQL: ordering by the related period
            // number needs a join, and the rows are all in memory anyway.
            ->sortBy(fn (TimetableSlot $slot) => $slot->period?->number ?? 0)
            ->values();
    }

    /**
     * How many marks exist for each slot on one date, keyed by
     * "class_subject_id-period_id".
     *
     * One grouped query rather than an exists() per slot: a teacher with six
     * periods would otherwise cost six extra round trips every time the page
     * is opened, and this is the most-used page in the LMS.
     *
     * @param  Collection<int, TimetableSlot>  $slots
     * @return array<string, int>
     */
    private function markedCounts(Collection $slots, Carbon $date): array
    {
        if ($slots->isEmpty()) {
            return [];
        }

        return Attendance::query()
            ->where('attendance_date', $date->toDateString())
            ->where(function (Builder $query) use ($slots): void {
                foreach ($slots as $slot) {
                    $query->orWhere(function (Builder $inner) use ($slot): void {
                        $inner->where('class_subject_id', $slot->class_subject_id)
                            ->where('period_id', $slot->period_id);
                    });
                }
            })
            ->selectRaw('class_subject_id, period_id, count(*) as total')
            ->groupBy('class_subject_id', 'period_id')
            ->get()
            ->mapWithKeys(fn ($row): array => [
                $row->class_subject_id.'-'.$row->period_id => (int) $row->total,
            ])
            ->all();
    }

    /**
     * How many students are actively enrolled in each of these classes, keyed
     * by class id.
     *
     * Counted per class rather than per slot, because a teacher teaching three
     * subjects in one class would otherwise show the same roll three times.
     *
     * @param  Collection<int, TimetableSlot>  $slots
     * @return array<int, int>
     */
    private function enrolledCounts(Collection $slots): array
    {
        $classIds = $slots
            ->pluck('classSubject.classModel.id')
            ->filter()
            ->unique()
            ->values();

        if ($classIds->isEmpty()) {
            return [];
        }

        return Enrollment::query()
            ->whereIn('class_id', $classIds)
            ->where('status', 'active')
            ->selectRaw('class_id, count(*) as total')
            ->groupBy('class_id')
            ->pluck('total', 'class_id')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }

    /**
     * The marking page for one subject, one period, one date.
     *
     * Read-only once a register exists. That is the whole point of this
     * method: a teacher who comes back to a period they already marked sees
     * what they wrote, and cannot change it from here.
     *
     * The date is a query parameter rather than always today, because a
     * teacher marks a forgotten sheet the next morning and the redirect after
     * saving has to be able to return them to the date they marked.
     */
    public function mark(Request $request): Response
    {
        $teacher = $request->user();

        abort_unless($teacher?->isTeacher(), 403);

        $classSubject = ClassSubject::with(['classModel.stream', 'subject'])
            ->findOrFail($request->integer('class_subject_id'));

        abort_if(
            $classSubject->teacher_id !== $teacher->id,
            403,
            'You do not teach this subject in this class.',
        );

        $period = Period::findOrFail($request->integer('period_id'));

        abort_if(
            (bool) $period->is_break,
            422,
            'Cannot mark attendance for a break period.',
        );

        $attendanceDate = $this->attendanceDate($request);

        $existing = $this->existingMarks($classSubject, $period, $attendanceDate);

        // Locked when anything at all has been written. A single row is proof
        // that the teacher submitted, and the unique index means a partial
        // save cannot leave the rest of the period open.
        $isLocked = $existing->isNotEmpty();

        return Inertia::render('Teacher/Attendance/Mark', [
            'class_subject' => [
                'id' => $classSubject->id,
                'subject_name' => $classSubject->subject?->name,
                'subject_code' => $classSubject->subject?->code,
            ],
            'class' => [
                'id' => $classSubject->classModel?->id,
                'display_name' => $classSubject->classModel?->displayName(),
                'grade_level' => $classSubject->classModel?->grade_level,
                'section' => $classSubject->classModel?->section,
                'stream_name' => $classSubject->classModel?->stream?->name,
            ],
            'period' => [
                'id' => $period->id,
                'number' => $period->number,
                'label' => $period->label,
                'start_time' => $period->start_time?->format('H:i'),
                'end_time' => $period->end_time?->format('H:i'),
            ],
            'attendance_date' => $attendanceDate->toDateString(),
            'students' => $this->roster($classSubject, $existing),
            'is_locked' => $isLocked,
            'locked_message' => $isLocked
                ? 'Attendance locked. Contact administration to change.'
                : null,
        ]);
    }

    /**
     * The date being marked, defaulting to today.
     *
     * The format is checked rather than trusted, because this string goes
     * straight into a date column comparison; anything unparseable would
     * otherwise match no rows and render a register as an empty roster.
     */
    private function attendanceDate(Request $request): Carbon
    {
        $date = $request->query('attendance_date');

        if (! is_string($date) || $date === '') {
            return Carbon::today();
        }

        abort_unless(
            Carbon::hasFormat($date, 'Y-m-d'),
            422,
            'The attendance date must be in Y-m-d format.',
        );

        return Carbon::parse($date)->startOfDay();
    }

    /**
     * The students on this class's roll for this session, in roll order.
     *
     * Scoped to the class's own session rather than to whichever session is
     * active right now: a class from last year is a historical document, and
     * last year's roll would be the wrong register to mark this year's class.
     *
     * @param  Collection<int, Attendance>  $existing
     * @return array<int, array<string, mixed>>
     */
    private function roster(ClassSubject $classSubject, Collection $existing): array
    {
        $sessionId = $classSubject->classModel?->academic_session_id;

        return Enrollment::query()
            ->where('class_id', $classSubject->class_id)
            ->where('academic_session_id', $sessionId)
            ->where('status', 'active')
            ->with(['studentProfile.user', 'studentProfile.batch'])
            ->get()
            ->sortBy(fn (Enrollment $enrollment) => $enrollment->studentProfile?->roll_number ?? '')
            ->map(function (Enrollment $enrollment) use ($existing): array {
                $student = $enrollment->studentProfile;
                $mark = $existing->get($enrollment->student_profile_id);

                return [
                    'student_profile_id' => $enrollment->student_profile_id,
                    'roll_number' => $student?->roll_number,
                    'name' => $student?->user?->name,
                    'batch_name' => $student?->batch?->name,

                    // The saved status, or null on an open register. The page
                    // reads this to decide between the four buttons and a
                    // read-only badge.
                    'status' => $mark?->status,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * The marks already written for this exact period, keyed by student.
     *
     * @return Collection<int, Attendance>
     */
    private function existingMarks(
        ClassSubject $classSubject,
        Period $period,
        Carbon $date,
    ): Collection {
        return Attendance::query()
            ->where('class_subject_id', $classSubject->id)
            ->where('period_id', $period->id)
            ->where('attendance_date', $date->toDateString())
            ->get()
            ->keyBy('student_profile_id');
    }

    /**
     * Save a whole register.
     *
     * A teacher may write a period exactly once. There is no second pass: if
     * anything already exists for this subject, period and date, the save is
     * refused and the page reloads read-only. That is the rule that makes an
     * attendance record evidence rather than an opinion, and it is why the
     * correction path is the administrator's rather than this form's.
     *
     * A transaction, because a half-written register is a state nobody can
     * display: the page would show a locked period with some students missing.
     */
    public function store(MarkAttendanceRequest $request): RedirectResponse
    {
        $teacher = $request->user();

        abort_unless($teacher?->isTeacher(), 403);

        $data = $request->validated();

        $classSubject = ClassSubject::with('classModel')->findOrFail($data['class_subject_id']);

        abort_if(
            $classSubject->teacher_id !== $teacher->id,
            403,
            'You do not teach this subject in this class.',
        );

        $period = Period::findOrFail($data['period_id']);

        abort_if(
            (bool) $period->is_break,
            422,
            'Cannot mark attendance for a break period.',
        );

        $date = Carbon::parse($data['attendance_date'])->startOfDay();

        // Refuse the whole save rather than merge into it. Checking here and
        // again inside the transaction would be belt and braces; this one is
        // what produces the message a teacher can act on.
        if ($this->alreadyMarked($classSubject, $period, $date)) {
            return back()->with(
                'error',
                'Attendance for this period is already marked and cannot be '
                .'changed. Contact administration to edit.',
            );
        }

        $sessionId = $classSubject->classModel?->academic_session_id;

        /*
         * Which of the submitted students are genuinely on this roll, read in
         * one query rather than one per row.
         *
         * Keyed by int rather than kept as a loose list: the payload arrives
         * with string ids from the form, and a strict in_array() over a list
         * of ints would compare "41" against 41, fail for every student, and
         * silently skip the whole register. A hash lookup is a hit either way.
         */
        $enrolledIds = Enrollment::query()
            ->where('class_id', $classSubject->class_id)
            ->where('academic_session_id', $sessionId)
            ->where('status', 'active')
            ->pluck('student_profile_id')
            ->map(fn ($id): int => (int) $id)
            ->flip()
            ->all();

        $marked = 0;
        $skipped = 0;

        DB::transaction(function () use ($data, $classSubject, $period, $date, $teacher, $enrolledIds, &$marked, &$skipped): void {
            foreach ($data['records'] as $record) {
                /*
                 * Skipped rather than rejected: the page only ever offers the
                 * current roll, so a student on the payload who has since left
                 * the class is a stale tab, not an attack. Failing the whole
                 * register would lose thirty good marks over one bad id.
                 */
                if (! array_key_exists((int) $record['student_profile_id'], $enrolledIds)) {
                    $skipped++;

                    continue;
                }

                Attendance::create([
                    'student_profile_id' => $record['student_profile_id'],
                    'class_subject_id' => $classSubject->id,
                    'period_id' => $period->id,
                    'attendance_date' => $date->toDateString(),
                    'status' => $record['status'],

                    // The teacher who submitted it, which is the same person
                    // the ownership guard above already proved owns the
                    // subject.
                    'marked_by' => $teacher->id,
                    'marked_at' => now(),
                    'notes' => null,
                ]);

                $marked++;
            }
        });

        if ($marked === 0) {
            return back()->with(
                'error',
                'None of the submitted students are enrolled in this class, '
                .'so nothing was marked.',
            );
        }

        $message = "Attendance marked for {$marked} student(s).";

        if ($skipped > 0) {
            $message .= " {$skipped} skipped (not enrolled).";
        }

        return redirect()
            ->route('teacher.attendance.mark', [
                'class_subject_id' => $classSubject->id,
                'period_id' => $period->id,
                'attendance_date' => $date->toDateString(),
            ])
            ->with('success', $message);
    }

    /**
     * Whether this period already has a register, locked or not.
     *
     * Soft-deleted rows count. A withdrawn mark is still a mark: the office
     * removing it does not hand the period back to the teacher.
     */
    private function alreadyMarked(
        ClassSubject $classSubject,
        Period $period,
        Carbon $date,
    ): bool {
        return Attendance::withTrashed()
            ->where('class_subject_id', $classSubject->id)
            ->where('period_id', $period->id)
            ->where('attendance_date', $date->toDateString())
            ->exists();
    }
}
