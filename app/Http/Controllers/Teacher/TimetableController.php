<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ClassSubject;
use App\Models\Period;
use App\Models\TimetableSlot;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class TimetableController extends Controller
{
    /**
     * The school week, 1 = Monday .. 6 = Saturday.
     *
     * The same list the admin builder uses, kept in one place so the
     * read-only view and the grid agree on what a day is.
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
     * Show the teacher their own weekly teaching schedule.
     *
     * Everything here is derived from the teacher's own assignments, so a
     * teacher can only ever see their own timetable.
     */
    public function index(Request $request): Response
    {
        $teacher = $request->user();

        // The route already requires the teacher role; this keeps the page
        // safe if the controller is reused from somewhere without it.
        abort_unless($teacher?->isTeacher(), 403);

        $assignments = ClassSubject::query()
            ->where('teacher_id', $teacher->id)
            ->with('classModel')
            ->get();

        $slots = TimetableSlot::query()
            ->whereIn('class_subject_id', $assignments->pluck('id')->all())
            ->with(['period', 'classSubject.subject', 'classSubject.classModel.stream'])
            ->get()
            ->map(fn (TimetableSlot $slot) => [
                'day_of_week' => $slot->day_of_week,
                'period_id' => $slot->period_id,
                'subject_name' => $slot->classSubject->subject->name,
                'subject_code' => $slot->classSubject->subject->code,
                // The reader is the teacher, so their own name is redundant.
                'teacher_name' => null,
                'room' => $slot->room,
                'class_display_name' => $slot->classSubject->classModel->displayName(),
            ])
            ->values();

        return Inertia::render('Teacher/Timetable', [
            'periods' => $this->periodsFor($assignments),
            'days' => self::DAYS,
            'slots' => $slots,
            'teacher' => ['name' => $teacher->name],
            'summary' => [
                'total_periods' => $slots->count(),
                'classes_count' => $slots
                    ->pluck('class_display_name')
                    ->unique()
                    ->count(),
            ],
            'daySummary' => collect(self::DAYS)
                ->mapWithKeys(fn (string $day, int $number) => [
                    $number => $slots->where('day_of_week', $number)->count(),
                ])
                ->all(),
        ]);
    }

    /**
     * The periods to draw the grid rows from.
     *
     * A teacher is shown the session their own classes belong to, because
     * that is the session their timetable was built in. A teacher with no
     * assignments at all falls back to the active session, so the grid still
     * has the right rows instead of collapsing to nothing.
     *
     * @param  Collection<int, ClassSubject>  $assignments
     * @return array<int, array<string, mixed>>
     */
    private function periodsFor(Collection $assignments): array
    {
        $sessionId = $assignments
            ->first(fn (ClassSubject $assignment) => $assignment->classModel !== null)
            ?->classModel
            ?->academic_session_id;

        $sessionId ??= AcademicSession::active()->value('id');

        if ($sessionId === null) {
            return [];
        }

        return Period::forSession($sessionId)
            ->map(fn (Period $period) => [
                'id' => $period->id,
                'number' => $period->number,
                'label' => $period->label,
                'start_time' => $period->start_time->format('H:i'),
                'end_time' => $period->end_time->format('H:i'),
                'is_break' => $period->is_break,
            ])
            ->values()
            ->all();
    }
}
