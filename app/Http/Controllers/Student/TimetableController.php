<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TimetableController extends Controller
{
    /**
     * The school week, 1 = Monday .. 6 = Saturday.
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
     * Show the student's own weekly timetable.
     *
     * Resolved through the student's own enrollment rather than any id from
     * the request, so a student can only ever be shown their own class's
     * grid.
     *
     * Both empty states are normal rather than broken: an account with no
     * profile, and a profile that is not in a class yet. The second is
     * every new admission for the first few days, so it says what to do
     * rather than showing an error.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $student = $user?->studentProfile;

        // The route already requires the student role; this keeps the page
        // safe if the controller is reused from somewhere without it.
        abort_unless($user?->isStudent(), 403);

        if (! $student) {
            return $this->emptyTimetable(
                $user,
                'Your student profile has not been created yet.',
            );
        }

        $enrollment = $student->currentEnrollment();

        if (! $enrollment) {
            return $this->emptyTimetable(
                $user,
                'You are not enrolled in a class yet. Please contact the administration.',
            );
        }

        $class = $enrollment->loadMissing(
            ['classModel' => fn ($query) => $query->with('stream')]
        )->classModel;

        if ($class === null) {
            // The enrollment points at a class that has since been removed.
            // Soft deletes mean this should not happen, but an empty grid is
            // a better answer than a null reference error.
            return $this->emptyTimetable(
                $user,
                'Your class is no longer available. Please contact the administration.',
            );
        }

        $periods = Period::forSession($class->academic_session_id)
            ->map(fn (Period $period) => [
                'id' => $period->id,
                'number' => $period->number,
                'label' => $period->label,
                'start_time' => $period->start_time?->format('H:i'),
                'end_time' => $period->end_time?->format('H:i'),
                'is_break' => (bool) $period->is_break,
            ])
            ->values()
            ->all();

        $slots = TimetableSlot::query()
            ->where('class_id', $class->id)
            ->with(['classSubject.subject', 'classSubject.teacher'])
            ->get()
            ->map(fn (TimetableSlot $slot) => [
                'day_of_week' => $slot->day_of_week,
                'period_id' => $slot->period_id,
                'subject_name' => $slot->classSubject?->subject?->name ?? '',
                'subject_code' => $slot->classSubject?->subject?->code,
                'teacher_name' => $slot->classSubject?->teacher?->name,
                'room' => $slot->room,
            ])
            ->values()
            ->all();

        return Inertia::render('Student/Timetable', [
            'noClass' => false,
            'message' => null,
            'periods' => $periods,
            'days' => self::DAYS,
            'slots' => $slots,
            'student' => ['name' => $user->name],
            'classInfo' => [
                'display_name' => $class->displayName(),
                'grade_level' => $class->grade_level,
                'section' => $class->section,
                'stream_name' => $class->stream?->name,
            ],
        ]);
    }

    /**
     * The no-class page, with the same prop shape as the real one.
     *
     * Built as one method rather than repeated at each early return, so the
     * grid page and the empty page can never drift apart in shape and leave
     * a prop undefined in the React.
     */
    private function emptyTimetable(User $user, string $message): Response
    {
        return Inertia::render('Student/Timetable', [
            'noClass' => true,
            'message' => $message,
            'periods' => [],
            'days' => self::DAYS,
            'slots' => [],
            'student' => ['name' => $user->name],
            'classInfo' => null,
        ]);
    }
}
