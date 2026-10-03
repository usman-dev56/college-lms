<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassModel;
use App\Models\Stream;
use App\Models\StudentBatch;
use App\Services\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports over the whole roll rather than over one class.
 *
 * Kept apart from AttendanceController on purpose: that one is the register
 * itself, where a row is a mark and editing it is a correction. This one only
 * reads - a defaulter list is a thing the office acts on by telephoning
 * parents, not by rewriting the register, so there is nothing here that
 * should be able to change a mark by accident.
 */
class AttendanceReportController extends Controller
{
    /**
     * Students below the board eligibility threshold.
     */
    public function defaulters(Request $request): Response
    {
        $filters = $this->filters($request);

        return Inertia::render('Admin/Attendance/Defaulters', [
            ...app(AttendanceService::class)->defaulters($filters),

            'batches' => StudentBatch::query()
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),

            'classes' => ClassModel::query()
                ->orderBy('grade_level')
                ->orderBy('section')
                ->get()
                ->map(fn (ClassModel $class): array => [
                    'id' => $class->id,
                    'display_name' => $class->displayName(),
                ])
                ->all(),

            'streams' => Stream::query()
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),

            'filters' => $filters,
        ]);
    }

    /**
     * The same list as a spreadsheet.
     *
     * Streamed rather than buffered because the file is built row by row from
     * an already-aggregated result and could be long on a large roll; nothing
     * holds the whole thing in memory in order to send it.
     */
    public function exportDefaulters(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);

        $report = app(AttendanceService::class)->defaulters($filters);

        $filename = 'defaulters-'.now()->toDateString().'.csv';

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            // Excel opens a CSV as one column per tab unless a byte order mark
            // tells it the file is UTF-8; without it the student's name comes
            // out as mojibake in the one program this file is for.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Roll Number',
                'Student Name',
                'Batch',
                'Class',
                'Present',
                'Absent',
                'Late',
                'Leave',
                'Total',
                'Percentage',
                'Shortfall',
            ], escape: '\\');

            foreach ($report['students'] as $student) {
                fputcsv($handle, [
                    $student['roll_number'],
                    $student['student_name'],
                    $student['batch_name'],

                    // Never a blank cell: an un-enrolled student is a real
                    // state, and an empty column would read as a missing
                    // value rather than as the answer.
                    $student['class_display_name'] ?? 'Not enrolled',

                    $student['present'],
                    $student['absent'],
                    $student['late'],
                    $student['leave'],
                    $student['total'],
                    number_format($student['percentage'], 2),
                    number_format($student['shortfall'], 2),
                ], escape: '\\');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * The filter values from the query string, normalised for the form.
     *
     * Ids are cast only when numeric, so a hand-edited URL cannot turn a
     * filter into a query against an unexpected value.
     *
     * @return array{batch_id:int|null, class_id:int|null, stream_id:int|null}
     */
    private function filters(Request $request): array
    {
        return [
            'batch_id' => $this->numericId($request->query('batch_id')),
            'class_id' => $this->numericId($request->query('class_id')),
            'stream_id' => $this->numericId($request->query('stream_id')),
        ];
    }

    /**
     * A positive integer id from a query value, or null.
     */
    private function numericId(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /**
     * One class, one day.
     *
     * A class is required rather than defaulted, because "today's attendance"
     * with no class named is the whole college at once - a different report, and
     * one that would need a date range to be readable.
     */
    public function daily(Request $request): Response|RedirectResponse
    {
        $filters = $this->dailyFilters($request);

        if ($filters['class_id'] === null) {
            return redirect()
                ->route('admin.attendance.index')
                ->with('error', 'Choose a class before opening the daily report.');
        }

        $report = app(AttendanceService::class)->dailyReport(
            $filters['class_id'],
            $filters['date'],
        );

        return Inertia::render('Admin/Reports/Attendance/Daily', [
            'report' => $report,
            'classes' => $this->classOptions(),
            'filters' => $filters,
        ]);
    }

    /**
     * One class across a range of dates.
     */
    public function range(Request $request): Response|RedirectResponse
    {
        $filters = $this->rangeFilters($request);

        if ($filters['class_id'] === null) {
            return redirect()
                ->route('admin.attendance.index')
                ->with('error', 'Choose a class before opening the range report.');
        }

        $report = app(AttendanceService::class)->rangeReport(
            $filters['class_id'],
            $filters['from'],
            $filters['to'],
        );

        return Inertia::render('Admin/Reports/Attendance/Range', [
            'report' => $report,
            'classes' => $this->classOptions(),
            'filters' => $filters,
        ]);
    }

    /**
     * The daily report as a spreadsheet, one row per period.
     */
    public function exportDaily(Request $request): StreamedResponse
    {
        $filters = $this->dailyFilters($request);

        abort_if($filters['class_id'] === null, 422, 'Choose a class first.');

        $report = app(AttendanceService::class)->dailyReport(
            $filters['class_id'],
            $filters['date'],
        );

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            // Without the byte order mark Excel reads the file as the local code
            // page and mangles any name that is not plain ASCII.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Period',
                'Subject',
                'Teacher',
                'Present',
                'Absent',
                'Late',
                'Leave',
                'Total',
                'Percentage',
            ], escape: '\\');

            foreach ($report['periods'] as $period) {
                fputcsv($handle, [
                    $period['period_label'],
                    $period['subject_name'],
                    $period['teacher_name'],
                    $period['present'],
                    $period['absent'],
                    $period['late'],
                    $period['leave'],
                    $period['total'],

                    // An empty cell for an unmarked period, rather than a zero
                    // that would read as a register of all absences.
                    $period['percentage'] === null
                        ? ''
                        : number_format($period['percentage'], 2),
                ], escape: '\\');
            }

            fclose($handle);
        }, 'daily-attendance-'.$filters['class_id'].'-'.$filters['date'].'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * The range report as a spreadsheet, one row per student.
     */
    public function exportRange(Request $request): StreamedResponse
    {
        $filters = $this->rangeFilters($request);

        abort_if($filters['class_id'] === null, 422, 'Choose a class first.');

        $report = app(AttendanceService::class)->rangeReport(
            $filters['class_id'],
            $filters['from'],
            $filters['to'],
        );

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Roll Number',
                'Student',
                'Present',
                'Absent',
                'Late',
                'Leave',
                'Total',
                'Percentage',
            ], escape: '\\');

            foreach ($report['students'] as $student) {
                fputcsv($handle, [
                    $student['roll_number'],
                    $student['student_name'],
                    $student['present'],
                    $student['absent'],
                    $student['late'],
                    $student['leave'],
                    $student['total'],
                    $student['percentage'] === null
                        ? ''
                        : number_format($student['percentage'], 2),
                ], escape: '\\');
            }

            fclose($handle);
        }, 'range-attendance-'.$filters['class_id'].'-'.$filters['from'].'-'.$filters['to'].'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * The classes offered in the report filter.
     *
     * Shared by all four report pages so the drop-down is ordered and labelled
     * identically wherever it appears.
     *
     * @return array<int, array{id: int, display_name: string}>
     */
    private function classOptions(): array
    {
        return ClassModel::query()
            ->orderBy('grade_level')
            ->orderBy('section')
            ->get()
            ->map(fn (ClassModel $class): array => [
                'id' => $class->id,
                'display_name' => $class->displayName(),
            ])
            ->all();
    }

    /**
     * The daily report's filters, with the date defaulting to today.
     *
     * @return array{class_id: int|null, date: string}
     */
    private function dailyFilters(Request $request): array
    {
        return [
            'class_id' => $this->numericId($request->query('class_id')),
            'date' => $this->dateOr($request->query('date'), now()->toDateString()),
        ];
    }

    /**
     * The range report's filters, defaulting to the last thirty days.
     *
     * Reversed dates are swapped rather than rejected: someone typing the two
     * boxes in the wrong order wants the report, not a lecture, and the swapped
     * range is exactly what they meant.
     *
     * @return array{class_id: int|null, from: string, to: string}
     */
    private function rangeFilters(Request $request): array
    {
        $to = $this->dateOr($request->query('to'), now()->toDateString());
        $from = $this->dateOr($request->query('from'), now()->subDays(30)->toDateString());

        return [
            'class_id' => $this->numericId($request->query('class_id')),
            'from' => $from > $to ? $to : $from,
            'to' => $from > $to ? $from : $to,
        ];
    }

    /**
     * A Y-m-d value from the query string, or the fallback.
     *
     * Checked rather than parsed and trusted, because this string goes straight
     * into a date comparison and "2026-13-45" is not a date.
     */
    private function dateOr(mixed $value, string $fallback): string
    {
        if (! is_string($value) || ! Carbon::hasFormat($value, 'Y-m-d')) {
            return $fallback;
        }

        return Carbon::parse($value)->format('Y-m-d') === $value ? $value : $fallback;
    }
}
