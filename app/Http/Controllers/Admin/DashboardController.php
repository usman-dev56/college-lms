<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Admission;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The administrator's landing page.
 *
 * Four questions, and nothing else: how big is the college, how is the roll
 * distributed, how full are the classes, and what is waiting to be dealt with.
 * Everything is a count or an aggregate over existing rows - there is no table
 * behind this page and nothing to keep in step.
 *
 * Attendance has its own pages, so none of it appears here beyond the defaulter
 * list: a dashboard that tried to carry the trend chart as well would answer
 * the attendance question badly and leave the rest of the college worse.
 *
 * Counts that depend on a session are taken from the active one, falling back
 * to the most recent. Without that fallback a college that has not activated
 * next year's session yet would show a dashboard of zeroes on the first day of
 * term, which reads as an empty college rather than as an unactivated session.
 */
class DashboardController extends Controller
{
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
        $session = $this->session();

        return Inertia::render('Admin/Dashboard', [
            'user' => ['name' => (string) $request->user()?->name],

            'metrics' => $this->metrics($session),
            'pendingAdmissions' => Admission::query()
                ->where('status', 'pending')
                ->count(),

            'studentsByStream' => $this->studentsByStream($session),
            'classCapacity' => $this->classCapacity($session),
            'topDefaulters' => $this->topDefaulters(),
            'recentAdmissions' => $this->recentAdmissions(),
        ]);
    }

    /**
     * The session the figures describe: the active one, or the latest.
     *
     * Never null, so no caller has to decide what an absent session means -
     * it returns the most recent instead, which is the best available answer.
     */
    private function session(): ?AcademicSession
    {
        return AcademicSession::current()
            ?? AcademicSession::query()
                ->orderByDesc('start_date')
                ->first();
    }

    /**
     * The five headline counts.
     *
     * @return array{total_students: int, total_teachers: int, total_classes: int, total_enrolled: int, total_unassigned: int}
     */
    private function metrics(?AcademicSession $session): array
    {
        // Both conditions, because either alone is wrong: a graduated profile is
        // still a row, and an account deactivated by the office is still a row.
        $students = StudentProfile::query()
            ->where('status', 'active')
            ->whereHas('user', fn (Builder $query) => $query->where('is_active', true))
            ->count();

        $enrolled = $session === null ? 0 : Enrollment::query()
            ->where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->count();

        return [
            'total_students' => $students,
            'total_teachers' => User::query()
                ->where('role', 'teacher')
                ->where('is_active', true)
                ->count(),
            'total_classes' => $session === null ? 0 : ClassModel::query()
                ->where('academic_session_id', $session->id)
                ->where('is_active', true)
                ->count(),
            'total_enrolled' => $enrolled,

            /*
                Floored at zero. The two counts come from different tables on
                purpose - every active profile against the current session's
                enrollments - so a student enrolled in last year's session
                leaves the second number smaller than the first, and a
                "negative unassigned" would be a puzzle rather than a figure.
             */
            'total_unassigned' => max(0, $students - $enrolled),
        ];
    }

    /**
     * The roll split by stream, and within each stream by grade.
     *
     * Counted through enrollments rather than through the batch, because a
     * stream is a property of the class a student is sitting in: a batch spans
     * streams, so grouping by batch would answer a different question than the
     * chart asks.
     *
     * Pivoted in PHP rather than in SQL. The chart wants one row per stream
     * carrying both grades as columns, and a grouped query returns one row per
     * stream-and-grade pair - folding those into a row each is arithmetic, not
     * something the database should be doing for a table this small.
     *
     * @return array<int, array{stream_name: string, grade_11_count: int, grade_12_count: int, total: int}>
     */
    private function studentsByStream(?AcademicSession $session): array
    {
        if ($session === null) {
            return [];
        }

        $rows = Enrollment::query()
            ->join('classes', 'classes.id', '=', 'enrollments.class_id')
            ->join('streams', 'streams.id', '=', 'classes.stream_id')
            ->where('enrollments.academic_session_id', $session->id)
            ->where('enrollments.status', 'active')
            ->groupBy('streams.name', 'classes.grade_level')
            // DISTINCT because a student can hold more than one enrollment in
            // a session; counting rows would double-count them.
            ->selectRaw(
                'streams.name as stream_name,
                 classes.grade_level,
                 COUNT(DISTINCT enrollments.student_profile_id) as student_count'
            )
            ->get();

        $byStream = [];

        foreach ($rows as $row) {
            $name = (string) $row->stream_name;
            $count = (int) $row->student_count;

            $byStream[$name] ??= [
                'stream_name' => $name,
                'grade_11_count' => 0,
                'grade_12_count' => 0,
                'total' => 0,
            ];

            $byStream[$name]['total'] += $count;

            // A grade the college does not teach still counts towards the
            // stream total; it just has no bar of its own, which is
            // better than quietly folding it into 11th.
            if ((int) $row->grade_level === 11) {
                $byStream[$name]['grade_11_count'] += $count;
            } elseif ((int) $row->grade_level === 12) {
                $byStream[$name]['grade_12_count'] += $count;
            }
        }

        ksort($byStream);

        return array_values($byStream);
    }

    /**
     * How full each active class is.
     *
     * withCount rather than a grouped join: it keeps classes with no students
     * at all in the result. A group-by join with an inner join drops an empty
     * class, and an empty class is exactly the one the office needs to see.
     *
     * A class with no capacity is reported with null utilisation rather than
     * omitted or shown as 0%. Not setting a capacity is a decision, not a full
     * class, and the two must not look alike.
     *
     * @return array<int, array{class_id: int, class_display_name: string, capacity: int|null, enrolled_count: int, utilization_percentage: float|null}>
     */
    private function classCapacity(?AcademicSession $session): array
    {
        if ($session === null) {
            return [];
        }

        return ClassModel::query()
            ->where('academic_session_id', $session->id)
            ->where('is_active', true)
            ->with('stream')
            ->withCount([
                'enrollments as enrolled_count' => fn (Builder $query) => $query
                    ->where('academic_session_id', $session->id)
                    ->where('status', 'active'),
            ])
            ->get()
            ->map(function (ClassModel $class): array {
                $capacity = $class->capacity === null ? null : (int) $class->capacity;
                $enrolled = (int) $class->enrolled_count;

                return [
                    'class_id' => $class->id,
                    'class_display_name' => $class->displayName(),
                    'capacity' => $capacity,
                    'enrolled_count' => $enrolled,
                    'utilization_percentage' => $capacity === null || $capacity === 0
                        ? null
                        : round($enrolled / $capacity * 100, 1),
                ];
            })
            ->sortBy('class_display_name')
            ->values()
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
                'created_at' => $admission->created_at?->format('Y-m-d H:i'),
            ])
            ->all();
    }
}
