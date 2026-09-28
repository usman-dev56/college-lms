<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateTimetableRequest;
use App\Models\ClassModel;
use App\Models\ClassSubject;
use App\Models\Period;
use App\Models\TimetableSlot;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TimetableController extends Controller
{
    /**
     * The school week, 1 = Monday .. 6 = Saturday.
     *
     * The day_of_week column stores these numbers and a CHECK constraint
     * keeps them inside this range, so this list is the single source of
     * truth for the grid and the validation.
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
     * Show the timetable builder for a class.
     */
    public function edit(ClassModel $class): Response
    {
        $data = $this->timetableData($class);

        return Inertia::render('Admin/Timetable/Edit', [
            'class' => $data['class'],
            'periods' => $data['periods'],
            'days' => self::DAYS,
            'classSubjects' => $data['classSubjects'],
            'existingSlots' => $data['slots'],
            'remaining' => $data['remaining'],
        ]);
    }

    /**
     * Show the timetable as a read-only grid.
     */
    public function show(ClassModel $class): Response
    {
        $data = $this->timetableData($class);

        return Inertia::render('Admin/Timetable/Show', [
            'class' => $data['class'],
            'periods' => $data['periods'],
            'days' => self::DAYS,
            // The read-only view resolves each cell to names, so the grid does
            // not have to join the ids back up in the browser.
            'slots' => $data['resolvedSlots'],
        ]);
    }

    /**
     * Replace the timetable of a class.
     *
     * The builder always submits the whole grid, so a save is a full rebuild:
     * the existing rows are soft deleted and the incoming ones inserted. That
     * keeps the partial unique index satisfied and leaves the previous
     * timetable on record.
     *
     * Nothing is written until every rule has been checked, so a rejected
     * save leaves the class exactly as it was.
     */
    public function update(UpdateTimetableRequest $request, ClassModel $class): RedirectResponse
    {
        $slots = $request->validated()['slots'];

        $assignments = ClassSubject::query()
            ->where('class_id', $class->id)
            ->with(['subject', 'teacher'])
            ->get()
            ->keyBy('id');

        if ($error = $this->rejectForeignRows($slots, $assignments)) {
            return back()->withInput()->with('error', $error);
        }

        $periods = Period::query()
            ->where('academic_session_id', $class->academic_session_id)
            ->get()
            ->keyBy('id');

        if ($error = $this->rejectBreakPeriods($slots, $periods)) {
            return back()->withInput()->with('error', $error);
        }

        if ($error = $this->rejectDuplicateCells($slots)) {
            return back()->withInput()->with('error', $error);
        }

        if ($error = $this->rejectTeacherConflicts($slots, $assignments, $class)) {
            return back()->withInput()->with('error', $error);
        }

        if ($error = $this->rejectUnbalancedWeek($slots, $assignments)) {
            return back()->withInput()->with('error', $error);
        }

        DB::transaction(function () use ($class, $slots): void {
            $class->timetableSlots()->get()->each(
                fn (TimetableSlot $slot) => $slot->delete()
            );

            foreach ($slots as $slot) {
                TimetableSlot::create([
                    'class_id' => $class->id,
                    'period_id' => $slot['period_id'],
                    'day_of_week' => $slot['day_of_week'],
                    'class_subject_id' => $slot['class_subject_id'],
                    'room' => $slot['room'] ?? null,
                ]);
            }
        });

        return redirect()
            ->route('admin.classes.timetable.edit', $class)
            ->with('success', 'Timetable saved successfully.');
    }

    /**
     * Everything the grid needs, for both the builder and the read-only view.
     *
     * @return array<string, mixed>
     */
    private function timetableData(ClassModel $class): array
    {
        $class->loadMissing(['stream', 'academicSession']);

        $assignments = ClassSubject::query()
            ->where('class_id', $class->id)
            ->with(['subject', 'teacher'])
            ->get();

        $classSubjects = $assignments
            ->map(fn (ClassSubject $assignment) => [
                'id' => $assignment->id,
                'subject_id' => $assignment->subject_id,
                'subject_name' => $assignment->subject->name,
                'subject_code' => $assignment->subject->code,
                'teacher_id' => $assignment->teacher_id,
                'teacher_name' => $assignment->teacher->name,
                'periods_per_week' => $assignment->periods_per_week,
            ])
            ->values();

        $periods = Period::forSession($class->academic_session_id)
            ->map(fn (Period $period) => [
                'id' => $period->id,
                'number' => $period->number,
                'label' => $period->label,
                'start_time' => $period->start_time->format('H:i'),
                'end_time' => $period->end_time->format('H:i'),
                'is_break' => $period->is_break,
            ])
            ->values();

        $slots = TimetableSlot::query()
            ->where('class_id', $class->id)
            ->orderBy('day_of_week')
            ->orderBy('period_id')
            ->get();

        // How many periods each assignment still needs, so the legend can
        // tell the admin how far the week is from complete.
        $scheduled = $slots->countBy('class_subject_id');

        $remaining = $assignments
            ->mapWithKeys(fn (ClassSubject $assignment) => [
                $assignment->id => $assignment->periods_per_week
                    - (int) $scheduled->get($assignment->id, 0),
            ])
            ->all();

        $resolved = TimetableSlot::forClass($class->id)
            ->map(fn (TimetableSlot $slot) => [
                'day_of_week' => $slot->day_of_week,
                'period_id' => $slot->period_id,
                'class_subject_id' => $slot->class_subject_id,
                'room' => $slot->room,
                'subject_name' => $slot->classSubject->subject->name,
                'subject_code' => $slot->classSubject->subject->code,
                'teacher_name' => $slot->classSubject->teacher->name,
            ])
            ->values();

        return [
            'class' => [
                'id' => $class->id,
                'display_name' => $class->displayName(),
                'grade_level' => $class->grade_level,
                'stream_name' => $class->stream?->name,
                'section' => $class->section,
                'room' => $class->room,
            ],
            'periods' => $periods,
            'classSubjects' => $classSubjects,
            'slots' => $slots
                ->map(fn (TimetableSlot $slot) => [
                    'id' => $slot->id,
                    'day_of_week' => $slot->day_of_week,
                    'period_id' => $slot->period_id,
                    'class_subject_id' => $slot->class_subject_id,
                    'room' => $slot->room,
                ])
                ->values(),
            'resolvedSlots' => $resolved,
            'remaining' => $remaining,
        ];
    }

    /**
     * Reject a cell whose assignment does not belong to this class.
     *
     * The request only proves the rows exist; it does not prove they belong
     * to the class being edited.
     *
     * @param  array<int, array<string, mixed>>  $slots
     * @param  Collection<int, ClassSubject>  $assignments
     */
    private function rejectForeignRows(
        array $slots,
        Collection $assignments
    ): ?string {
        foreach ($slots as $slot) {
            if (! $assignments->has($slot['class_subject_id'])) {
                return 'Subject does not belong to this class.';
            }
        }

        return null;
    }

    /**
     * Reject a cell that falls inside a break, or uses a period from another
     * session.
     *
     * Breaks stay in the grid so the timetable lines up, but nothing is taught
     * in them.
     *
     * @param  array<int, array<string, mixed>>  $slots
     * @param  SupportCollection<int, Period>  $periods
     */
    private function rejectBreakPeriods(
        array $slots,
        SupportCollection $periods
    ): ?string {
        foreach ($slots as $slot) {
            $period = $periods->get($slot['period_id']);

            if ($period === null) {
                return 'Cannot schedule a period that does not belong to this class\'s session.';
            }

            if ($period->is_break) {
                return 'Cannot schedule subjects during break periods.';
            }
        }

        return null;
    }

    /**
     * Reject a grid that fills the same cell twice.
     *
     * The database would refuse this too, but catching it here turns a query
     * exception into a message the admin can act on.
     *
     * @param  array<int, array<string, mixed>>  $slots
     */
    private function rejectDuplicateCells(array $slots): ?string
    {
        $seen = [];

        foreach ($slots as $slot) {
            $key = $slot['day_of_week'].'-'.$slot['period_id'];

            if (isset($seen[$key])) {
                return 'The same period was scheduled twice on the same day.';
            }

            $seen[$key] = true;
        }

        return null;
    }

    /**
     * Reject a save that would put one teacher in two places at once.
     *
     * A teacher can only be in one room at a time, so the same teacher may
     * not be scheduled in two different classes at the same day and period.
     * This lives here rather than in the database because the rule spans two
     * tables and needs a human-readable explanation.
     *
     * @param  array<int, array<string, mixed>>  $slots
     * @param  Collection<int, ClassSubject>  $assignments
     */
    private function rejectTeacherConflicts(
        array $slots,
        Collection $assignments,
        ClassModel $class
    ): ?string {
        if ($slots === []) {
            return null;
        }

        // What this save wants to book, per cell.
        $incoming = [];

        foreach ($slots as $slot) {
            $assignment = $assignments->get($slot['class_subject_id']);

            $incoming[$slot['day_of_week'].'-'.$slot['period_id']] = [
                'teacher_id' => $assignment->teacher_id,
                'teacher_name' => $assignment->teacher->name,
                'subject_name' => $assignment->subject->name,
            ];
        }

        $days = array_values(array_unique(array_column($slots, 'day_of_week')));
        $periodIds = array_values(array_unique(array_column($slots, 'period_id')));
        $teacherIds = array_values(array_unique(array_map(
            fn (array $cell) => $cell['teacher_id'],
            $incoming
        )));

        // What other classes have already booked in the same cells. The rows
        // are narrowed by day and period here and matched exactly below, so a
        // wide candidate set cannot produce a false conflict.
        $existing = DB::table('timetable_slots as ts')
            ->join('class_subjects as cs', 'cs.id', '=', 'ts.class_subject_id')
            ->join('classes as c', 'c.id', '=', 'ts.class_id')
            ->whereNull('ts.deleted_at')
            ->whereNull('cs.deleted_at')
            ->where('ts.class_id', '!=', $class->id)
            ->whereIn('ts.day_of_week', $days)
            ->whereIn('ts.period_id', $periodIds)
            ->whereIn('cs.teacher_id', $teacherIds)
            ->get([
                'ts.day_of_week',
                'ts.period_id',
                'ts.class_id',
                'cs.teacher_id',
                'c.grade_level',
                'c.section',
            ]);

        // The conflicting teacher is always one this save is booking, so the
        // name is always available from the incoming cells. Keyed by teacher
        // id so the lookup below is a single array access.
        $teacherNames = [];

        foreach ($incoming as $cell) {
            $teacherNames[$cell['teacher_id']] = $cell['teacher_name'];
        }

        $conflicts = [];

        foreach ($existing as $row) {
            $key = $row->day_of_week.'-'.$row->period_id;
            $wanted = $incoming[$key] ?? null;

            if ($wanted === null || $wanted['teacher_id'] !== $row->teacher_id) {
                continue;
            }

            $conflicts[] = sprintf(
                '%s is already teaching in grade %s section %s on %s',
                $teacherNames[$row->teacher_id] ?? ('Teacher #'.$row->teacher_id),
                $row->grade_level,
                $row->section,
                self::DAYS[$row->day_of_week] ?? ('day '.$row->day_of_week)
            );
        }

        if ($conflicts === []) {
            return null;
        }

        return 'A teacher is double-booked. '.$this->sentence($conflicts);
    }

    /**
     * Reject a save unless every subject is scheduled for exactly the number
     * of periods it is allocated.
     *
     * The week is all-or-nothing: a partially filled grid would quietly teach
     * less than the syllabus promises.
     *
     * @param  array<int, array<string, mixed>>  $slots
     * @param  Collection<int, ClassSubject>  $assignments
     */
    private function rejectUnbalancedWeek(
        array $slots,
        Collection $assignments
    ): ?string {
        $scheduled = collect($slots)->countBy('class_subject_id');

        $problems = [];

        foreach ($assignments as $assignment) {
            $expected = $assignment->periods_per_week;
            $actual = (int) $scheduled->get($assignment->id, 0);

            if ($actual !== $expected) {
                $problems[] = sprintf(
                    '%s needs %d period%s but has %d scheduled',
                    $assignment->subject->name,
                    $expected,
                    $expected === 1 ? '' : 's',
                    $actual
                );
            }
        }

        if ($problems === []) {
            return null;
        }

        return 'Every subject must fill its weekly allocation. '.$this->sentence($problems);
    }

    /**
     * Join a list of problems into one readable sentence.
     *
     * @param  array<int, string>  $items
     */
    private function sentence(array $items): string
    {
        if (count($items) === 1) {
            return $items[0].'.';
        }

        $last = array_pop($items);

        return implode('; ', $items).'; and '.$last.'.';
    }
}
