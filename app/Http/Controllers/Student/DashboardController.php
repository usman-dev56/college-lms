<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\TimetableSlot;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
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
     * The student's own landing page.
     *
     * Everything here is derived from the signed-in account. Nothing is
     * looked up by an id from the request, so there is no way for one
     * student to see another's record by editing a query string.
     *
     * Two states are deliberately normal rather than broken: having no
     * profile at all, and having a profile but no class. The first means the
     * account exists but the office has not admitted them; the second is
     * every new admission for the first few days. Both render a plain
     * message instead of an error.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $student = $user->studentProfile;

        // The route already requires the student role; this keeps the page
        // safe if the controller is ever reached without it.
        abort_unless($user?->isStudent(), 403);

        if (! $student) {
            return Inertia::render('Student/Dashboard', [
                'student' => null,
                'classInfo' => null,
                'todaySchedule' => [],
                'weekSummary' => [],
                'today_name' => self::DAYS[now()->dayOfWeekIso] ?? '',
                'message' => 'Your student profile has not been created yet. Please contact the administration.',
            ]);
        }

        $student->load(['batch']);

        $enrollment = $student->currentEnrollment();
        $classInfo = null;
        $todaySchedule = [];
        $weekSummary = [];

        if ($enrollment !== null) {
            // loadMissing rather than re-querying the relation: the enrollment
            // was just resolved so its class is not loaded yet, and one
            // eager load is clearer than a fresh query.
            $class = $enrollment->loadMissing(
                ['classModel' => fn ($query) => $query->with('stream')]
            )->classModel;

            if ($class !== null) {
                $classInfo = [
                    'id' => $class->id,
                    'display_name' => $class->displayName(),
                    'grade_level' => $class->grade_level,
                    'section' => $class->section,
                    'stream_name' => $class->stream?->name,
                    'room' => $class->room,
                ];

                $todaySchedule = $this->todaySchedule($class->id);
                $weekSummary = $this->weekSummary($class->id);
            }
        }

        return Inertia::render('Student/Dashboard', [
            'student' => [
                'name' => $user->name,
                'roll_number' => $student->roll_number,
                'batch_name' => $student->batch?->name,
                'status' => $student->status,
            ],
            'classInfo' => $classInfo,
            'todaySchedule' => $todaySchedule,
            'weekSummary' => $weekSummary,

            // Sunday is not in DAYS, so a Sunday visit reads as blank rather
            // than as an out-of-range notice.
            'today_name' => self::DAYS[now()->dayOfWeekIso] ?? '',
            'message' => null,
        ]);
    }

    /**
     * Today's periods for a class, in teaching order.
     *
     * Empty on a Sunday, which is a real answer rather than a gap: the
     * school week runs Monday to Saturday and there is nothing to show.
     *
     * @return array<int, array<string, mixed>>
     */
    private function todaySchedule(int $classId): array
    {
        $today = now()->dayOfWeekIso;

        if (! isset(self::DAYS[$today])) {
            return [];
        }

        return TimetableSlot::query()
            ->where('class_id', $classId)
            ->where('day_of_week', $today)
            ->with(['period', 'classSubject.subject', 'classSubject.teacher'])
            ->get()
            // Sorted in PHP rather than in SQL: ordering by the related
            // period number would need a join, and the rows are all in
            // memory regardless at this size.
            ->sortBy(fn (TimetableSlot $slot) => $slot->period?->number ?? 0)
            ->map(fn (TimetableSlot $slot) => [
                'period_label' => $slot->period?->label,
                'start_time' => $slot->period?->start_time?->format('H:i'),
                'end_time' => $slot->period?->end_time?->format('H:i'),
                'is_break' => (bool) $slot->period?->is_break,
                'subject_name' => $slot->classSubject?->subject?->name,
                'teacher_name' => $slot->classSubject?->teacher?->name,
                'room' => $slot->room,
            ])
            ->values()
            ->all();
    }

    /**
     * How many teaching periods each day has, for the week summary.
     *
     * Breaks are excluded: the card is about how much teaching there is, and
     * a break is not a lesson. One grouped query rather than six.
     *
     * @return array<int, array<string, mixed>>
     */
    private function weekSummary(int $classId): array
    {
        $counts = TimetableSlot::query()
            ->where('class_id', $classId)
            ->whereHas('period', fn ($query) => $query->where('is_break', false))
            ->selectRaw('day_of_week, count(*) as total')
            ->groupBy('day_of_week')
            ->pluck('total', 'day_of_week')
            ->all();

        $summary = [];

        foreach (self::DAYS as $number => $name) {
            $summary[] = [
                'day_number' => $number,
                'day_name' => $name,
                'periods_count' => (int) ($counts[$number] ?? 0),
            ];
        }

        return $summary;
    }
}
