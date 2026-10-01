<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassModel;
use App\Models\Stream;
use App\Models\StudentBatch;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
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
}
