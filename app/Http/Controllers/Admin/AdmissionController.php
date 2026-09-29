<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectAdmissionRequest;
use App\Models\Admission;
use App\Models\Stream;
use App\Models\StudentBatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class AdmissionController extends Controller
{
    /**
     * How many applications fit on one page.
     */
    private const PER_PAGE = 20;

    /**
     * The statuses an application can be in, in the order the office works
     * through them. The counts row and the status filter are both built from
     * this, so a status cannot appear in one and be missing from the other.
     *
     * @var array<int, string>
     */
    private const STATUSES = ['pending', 'reviewed', 'accepted', 'rejected', 'enrolled'];

    /**
     * List applications.
     *
     * The status counts are deliberately computed over the whole table and
     * not over the filtered result: the badges are how the office knows what
     * is waiting, and counts that shrank to match the current filter would
     * hide the very backlog the page exists to surface.
     */
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $admissions = Admission::query()
            ->with(['batch', 'streamApplied'])
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $query) use ($filters): void {
                    // ILIKE, not LIKE: PostgreSQL's LIKE is case-sensitive,
                    // so searching "hamza" would never find "Hamza Yousaf".
                    // The term is bound rather than interpolated, and the
                    // orWhere stays inside this closure so it cannot escape
                    // the filters wrapped around it.
                    $term = '%'.$filters['search'].'%';

                    $query->where('applicant_name', 'ilike', $term)
                        ->orWhere('application_number', 'ilike', $term)
                        ->orWhere('cnic_bform', 'ilike', $term);
                });
            })
            ->when($filters['batch_id'] !== null, function (Builder $query) use ($filters): void {
                $query->where('batch_id', $filters['batch_id']);
            })
            ->when($filters['stream_id'] !== null, function (Builder $query) use ($filters): void {
                $query->where('stream_applied_id', $filters['stream_id']);
            })
            ->when($filters['status'] !== 'all', function (Builder $query) use ($filters): void {
                $query->where('status', $filters['status']);
            })
            // Newest first. Applications arrive in a burst around the
            // deadline, so "most recent" is the order the office reads them in.
            ->latest('created_at')
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Admission $admission) => $this->admissionSummary($admission));

        return Inertia::render('Admin/Admissions/Index', [
            'admissions' => $admissions,
            'batches' => $this->batchOptions(),
            'streams' => $this->streamOptions(),
            'filters' => $filters,
            'counts' => $this->statusCounts(),
        ]);
    }

    /**
     * Show one application.
     *
     * reviewedBy and the enrolled student are eager loaded so the review
     * panel can name who decided, and the "Convert to Student" panel can link
     * to the student record once one exists.
     */
    public function show(Admission $admission): Response
    {
        $admission->load(['batch', 'streamApplied', 'reviewedBy', 'enrolledStudentProfile.user']);

        return Inertia::render('Admin/Admissions/Show', [
            'admission' => [
                'id' => $admission->id,
                'application_number' => $admission->application_number,
                'applicant_name' => $admission->applicant_name,
                'father_name' => $admission->father_name,
                'cnic_bform' => $admission->cnic_bform,
                'date_of_birth' => $admission->date_of_birth?->toDateString(),
                'phone' => $admission->phone,
                'guardian_phone' => $admission->guardian_phone,
                'address' => $admission->address,
                'previous_school' => $admission->previous_school,
                'previous_marks_obtained' => $admission->previous_marks_obtained,
                'previous_marks_total' => $admission->previous_marks_total,
                'merit_percentage' => $admission->merit_percentage,
                'merit_rank' => $admission->merit_rank,
                'status' => $admission->status,
                'rejection_reason' => $admission->rejection_reason,
                'reviewed_at' => $admission->reviewed_at?->toDateString(),
                'reviewed_by_name' => $admission->reviewedBy?->name,
                'created_at' => $admission->created_at?->toDateString(),
                'stream_name' => $admission->streamApplied?->name,
                'batch_name' => $admission->batch?->name,

                // Null until sub-stage 3.3 Part 3 converts the application
                // into a student record. The key is sent as null so the action
                // panel already has the shape it needs.
                'enrolled_student_profile_id' => $admission->enrolled_student_profile_id,
                'enrolled_student_name' => $admission->enrolledStudentProfile?->user?->name,
            ],
        ]);
    }

    /**
     * Mark an application as seen without deciding on it.
     *
     * Only from 'pending'. There is nothing to review twice, and re-opening a
     * decided application to mark it seen again would quietly undo the guard
     * that stops accept and reject touching a settled application.
     */
    public function review(Admission $admission): RedirectResponse
    {
        $this->ensureStatus($admission, ['pending']);

        $admission->update([
            'status' => 'reviewed',
            ...$this->reviewStamp(),
        ]);

        return back()->with('success', 'Application marked as reviewed.');
    }

    /**
     * Offer the applicant a place.
     *
     * Allowed from 'pending' as well as 'reviewed', because an office that
     * has already read an application may well accept it without a second
     * round of marking - forcing a pointless 'reviewed' first would just add
     * a step to the common path.
     */
    public function accept(Admission $admission): RedirectResponse
    {
        $this->ensureStatus($admission, ['pending', 'reviewed']);

        $admission->update([
            'status' => 'accepted',
            ...$this->reviewStamp(),
        ]);

        return back()->with('success', 'Application accepted.');
    }

    /**
     * Turn an application down, recording why.
     *
     * The reason is required by the form request, and the reason is kept on
     * the row afterwards: the office gets asked what the decision was, and
     * the applicant is entitled to an answer.
     */
    public function reject(RejectAdmissionRequest $request, Admission $admission): RedirectResponse
    {
        $this->ensureStatus($admission, ['pending', 'reviewed']);

        $admission->update([
            'status' => 'rejected',
            'rejection_reason' => $request->validated()['rejection_reason'],
            ...$this->reviewStamp(),
        ]);

        return back()->with('success', 'Application rejected.');
    }

    /**
     * Refuse a status transition that is not allowed from where the
     * application currently stands.
     *
     * 422 rather than a redirect: the request was well-formed, it was the
     * state of the record that made it meaningless. A redirect with a flash
     * would be indistinguishable from success in the browser, and the admin
     * would think the accept had gone through.
     */
    private function ensureStatus(Admission $admission, array $allowed): void
    {
        abort_unless(in_array($admission->status, $allowed, true), 422, 'Invalid status transition.');
    }

    /**
     * Who decided, and when - stamped on every action that decides anything.
     *
     * @return array<string, mixed>
     */
    private function reviewStamp(): array
    {
        return [
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
        ];
    }

    /**
     * An application as the list needs it.
     *
     * @return array<string, mixed>
     */
    private function admissionSummary(Admission $admission): array
    {
        return [
            'id' => $admission->id,
            'application_number' => $admission->application_number,
            'applicant_name' => $admission->applicant_name,
            'father_name' => $admission->father_name,
            'cnic_bform' => $admission->cnic_bform,
            'phone' => $admission->phone,
            'stream_name' => $admission->streamApplied?->name,
            'batch_name' => $admission->batch?->name,
            'status' => $admission->status,
            'merit_percentage' => $admission->merit_percentage,
            'merit_rank' => $admission->merit_rank,
            'created_at' => $admission->created_at?->toDateString(),
        ];
    }

    /**
     * How many applications sit in each status.
     *
     * One grouped query rather than five counts, and every status is present
     * in the result whether or not it has rows - a missing key would leave
     * the page unable to show a zero badge.
     *
     * @return array<string, int>
     */
    private function statusCounts(): array
    {
        $counts = Admission::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $result = [];

        foreach (self::STATUSES as $status) {
            $result[$status] = (int) ($counts[$status] ?? 0);
        }

        return $result;
    }

    /**
     * Batches for the filter drop-down.
     *
     * Every batch, not just the open ones: an application made last year is
     * still on file and still needs finding, and filtering to open batches
     * would make it unreachable.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function batchOptions(): Collection
    {
        return StudentBatch::query()
            ->orderByDesc('name')
            ->get()
            ->map(fn (StudentBatch $batch) => [
                'id' => $batch->id,
                'name' => $batch->name,
            ]);
    }

    /**
     * Active streams for the filter drop-down.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function streamOptions(): Collection
    {
        return Stream::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn (Stream $stream) => [
                'id' => $stream->id,
                'name' => $stream->name,
                'code' => $stream->code,
            ]);
    }

    /**
     * The filter values from the query string, normalised for the form.
     *
     * Ids are cast only when they are numeric, so a hand-edited URL cannot
     * turn a filter into a query against an unexpected value. The status is
     * checked against the known list and falls back to 'all' rather than
     * being passed through unfiltered.
     *
     * @return array{search:string, batch_id:int|null, stream_id:int|null, status:string}
     */
    private function filters(Request $request): array
    {
        $status = (string) $request->query('status', 'all');

        return [
            'search' => trim((string) $request->query('search', '')),
            'batch_id' => $this->numericId($request->query('batch_id')),
            'stream_id' => $this->numericId($request->query('stream_id')),
            'status' => in_array($status, self::STATUSES, true) ? $status : 'all',
        ];
    }

    /**
     * Read a positive integer id from a query value, or null.
     */
    private function numericId(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
