<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreAdmissionApplicationRequest;
use App\Models\Admission;
use App\Models\Stream;
use App\Models\StudentBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdmissionController extends Controller
{
    /**
     * Show the public application form.
     *
     * The page renders either way. When no batch is accepting applications
     * the visitor gets an explanation rather than an error or an empty
     * drop-down, because "admissions are closed" is a real answer and a
     * 500 or a blank select would read as a broken site.
     */
    public function create(): Response
    {
        // Only batches that are actually accepting applications, newest
        // cohort first: a college takes Grade 11 intake, so the most recent
        // batch is the one an applicant is almost always after.
        $batches = StudentBatch::query()
            ->where('admissions_open', true)
            ->orderByDesc('name')
            ->get()
            ->map(fn (StudentBatch $batch) => [
                'id' => $batch->id,
                'name' => $batch->name,
                'start_grade' => $batch->start_grade,
            ]);

        $streams = Stream::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn (Stream $stream) => [
                'id' => $stream->id,
                'name' => $stream->name,
                'code' => $stream->code,
            ]);

        return Inertia::render('Public/Admissions/Create', [
            'batches' => $batches,
            'streams' => $streams,
            'admissionsClosed' => $batches->isEmpty(),
        ]);
    }

    /**
     * Store an application from the public form.
     *
     * Wrapped in a transaction because reading the highest existing
     * application number and inserting the next one is a read followed by a
     * write, and two applicants submitting at the same moment could
     * otherwise both be handed the same number - which the unique index
     * would then reject, losing one of them. Inside a transaction the
     * second one simply waits and reads the first.
     *
     * The payload is built by hand rather than passed through validated(),
     * so nothing an applicant submits can set a status, a rank or a
     * reviewer. Every application starts as 'pending'.
     */
    public function store(StoreAdmissionApplicationRequest $request): RedirectResponse
    {
        $admission = DB::transaction(function () use ($request): Admission {
            return Admission::create([
                ...$request->safe()->only([
                    'applicant_name',
                    'father_name',
                    'cnic_bform',
                    'date_of_birth',
                    'phone',
                    'guardian_phone',
                    'address',
                    'previous_school',
                    'previous_marks_obtained',
                    'previous_marks_total',
                    'stream_applied_id',
                    'batch_id',
                ]),

                // The calendar year, not the academic one. An application
                // filed in September 2026 belongs to the 2026 series even
                // though the batch it names is called 2026-2028.
                'application_number' => Admission::generateApplicationNumber(
                    (int) date('Y'),
                ),
                'status' => 'pending',
            ]);
        });

        return redirect()
            ->route('admissions.success', [
                'application_number' => $admission->application_number,
            ])
            ->with('success', 'Your application has been received.');
    }

    /**
     * Confirm that an application was received.
     *
     * Reachable by anyone holding the application number - there is no login
     * to check against, which is the point. Only a handful of fields are
     * sent to the page: enough to confirm the right application was found,
     * and deliberately not the applicant's address, marks or phone, which
     * have no business being readable by anyone who guesses or is handed a
     * number.
     */
    public function success(string $application_number): Response
    {
        $admission = Admission::query()
            ->where('application_number', $application_number)
            ->with(['streamApplied', 'batch'])
            ->firstOrFail();

        return Inertia::render('Public/Admissions/Success', [
            'application_number' => $admission->application_number,
            'applicant_name' => $admission->applicant_name,
            'stream_name' => $admission->streamApplied?->name,
            'batch_name' => $admission->batch?->name,
            'submitted_at' => $admission->created_at?->toDateString(),
        ]);
    }
}
