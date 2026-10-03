<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Admission;
use App\Models\Attendance;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The administrator's landing page.
 *
 * A dashboard is only useful if it answers the question the person opening it
 * actually has, so this gathers four of them and nothing else: how big is the
 * college, how is attendance going, who needs a telephone call, and what is
 * waiting in the admissions queue. Everything here is a count or an aggregate
 * over existing rows - no table of its own, and nothing to keep in step.
 *
 * Every figure is scoped to the active session where one applies, so a
 * dashboard opened in a new academic year does not silently keep counting last
 * year's classes.
 */
class DashboardController extends Controller
{
    /**
     * How many days of trend to chart.
     *
     * A term's worth of history does not fit on a card, and the thirty-day
     * window is long enough for a weekly pattern to be visible.
     */
    private const TREND_DAYS = 30;

    /**
     * How many defaulters and admissions to name.
     *
     * The dashboard points at the problem; the list behind the footer link is
     * where the work gets done. Ten rows would be unreadable at card height.
     */
    private const TOP_DEFAULTERS = 5;

    private const RECENT_ADMISSIONS = 5;

    public function index(Request $request): Response
    {
        $session = AcademicSession::current();

        return Inertia::render('Admin/Dashboard', [
            'user' => ['name' => (string) $request->user()?->name],

            'metrics' => [
                'total_students' => $this->activeStudentCount(),
                'total_teachers' => User::query()
                    ->where('role', 'teacher')
                    ->where('is_active', true)
                    ->count(),
                'total_classes' => $session === null ? 0 : ClassModel::query()
                    ->where('academic_session_id', $session->id)
                    ->where('is_active', true)
                    ->count(),
                'total_admissions_pending' => Admission::query()
                    ->where('status', 'pending')
                    ->count(),
            ],

            'attendance' => $this->todaysAttendance(),

            'studentsByStream' => $this->studentsByStream($session),

            // Only the series, not the whole report: the page draws a chart
            // and needs four numbers per day, not the envelope around them.
            'attendanceTrend' => app(AttendanceService::class)
                ->trend(self::TREND_DAYS)['data'],

            'topDefaulters' => $this->topDefaulters(),

            'recentAdmissions' => $this->recentAdmissions(),
        ]);
    }

    /**
     * Students who are both on the roll and still able to attend.
     *
     * Both conditions, because either alone is wrong: a graduated profile is
     * still a row, and an account deactivated by the office is still a row.
     */
    private function activeStudentCount(): int
    {
        return StudentProfile::query()
            ->where('status', 'active')
            ->whereHas('user', fn (Builder $query) => $query->where('is_active', true))
            ->count();
    }

    /**
     * Today's register, and the rate it works out to.
     *
     * Read from the Attendance rows rather than through AttendanceService,
     * which reports over a class or a date range rather than over a whole
     * college on one day. Late and leave count as present, matching every other
     * percentage in the system, so the number on this card cannot disagree
     * with the number on a student's page.
     *
     * @return array{today_total: int, today_present: int, today_percentage: float|null}
     */
    private function todaysAttendance(): array
    {
        $today = Attendance::query()
            ->whereDate('attendance_date', Carbon::today());

        $total = (clone $today)->count();
        $present = $today->present()->count();

        // Null rather than zero: a college that has not marked anything this
        // morning has an unknown attendance, which is not the same statement as
        // an attendance of zero.
        return [
            'today_total' => $total,
            'today_present' => $present,
            'today_percentage' => $total === 0
                ? null
                : round($present / $total * 100, 2),
        ];
    }

    /**
     * The roll split by stream, largest first.
     *
     * Counted through enrollments rather than through the batch, because a
     * stream is a property of the class a student is sitting in: a batch spans
     * streams, so grouping by batch would answer a different question than the
     * chart asks.
     *
     * @return array<int, array{stream_name: string, student_count: int}>
     */
    private function studentsByStream(?AcademicSession $session): array
    {
        if ($session === null) {
            return [];
        }

        return Enrollment::query()
            ->join('classes', 'classes.id', '=', 'enrollments.class_id')
            ->join('streams', 'streams.id', '=', 'classes.stream_id')
            ->where('enrollments.academic_session_id', $session->id)
            ->where('enrollments.status', 'active')
            ->groupBy('streams.name')
            // DISTINCT because a student can hold more than one enrollment in
            // a session; counting rows would double-count them.
            ->selectRaw('streams.name as stream_name, COUNT(DISTINCT enrollments.student_profile_id) as student_count')
            ->orderByDesc('student_count')
            ->orderBy('streams.name')
            ->get()
            ->map(fn ($row): array => [
                'stream_name' => (string) $row->stream_name,
                'student_count' => (int) $row->student_count,
            ])
            ->all();
    }

    /**
     * The students furthest below the attendance mark.
     *
     * Read from the same defaulters() list the office works from, so the
     * dashboard cannot disagree with the page behind its link. Null
     * percentages are already excluded there: nobody having been marked yet is
     * unknown, not low.
     *
     * @return array<int, array<string, mixed>>
     */
    private function topDefaulters(): array
    {
        $students = app(AttendanceService::class)->defaulters()['students'];

        return array_slice($students, 0, self::TOP_DEFAULTERS);
    }

    /**
     * The newest applications, for the office to see what is waiting.
     *
     * Created date rather than reviewed date, because the question here is
     * "what has come in", and a reviewed application that has been sitting for
     * a month is not new.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recentAdmissions(): array
    {
        return Admission::query()
            ->with(['streamApplied', 'batch'])
            ->latest()
            ->take(self::RECENT_ADMISSIONS)
            ->get()
            ->map(fn (Admission $admission): array => [
                'id' => $admission->id,

                // Fall back to the application number rather than an empty
                // cell: a row with no name still has to be identifiable.
                'applicant_name' => (string) ($admission->applicant_name
                    ?: $admission->application_number),
                'stream_name' => $admission->streamApplied?->name,
                'batch_name' => $admission->batch?->name,
                'status' => (string) $admission->status,
                'created_at' => $admission->created_at?->format('j M Y'),
            ])
            ->all();
    }
}
