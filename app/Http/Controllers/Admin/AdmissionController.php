<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectAdmissionRequest;
use App\Models\AcademicSession;
use App\Models\Admission;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Stream;
use App\Models\StudentBatch;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    /**
     * Rank the applications for a batch by matric percentage.
     *
     * Only 'pending' and 'reviewed' are ranked. Accepted, rejected and
     * enrolled applications are already decided, and leaving them in would
     * imply a decision is still open on them.
     *
     * The ranks are written back to the row as they are assigned, so the
     * number the office printed last term still matches what the page shows
     * now. Recomputing on every load is deliberate: an applicant who
     * corrects their marks, or a late one that arrives, moves the list, and a
     * stored-but-stale rank would quietly disagree with the ordering.
     */
    public function meritList(Request $request): Response
    {
        $batches = $this->batchOptions();

        // The batch is required, but a bare /admin/admissions/merit-list has
        // nothing to rank, so it falls back to the first batch rather than
        // erroring. The page is useful on arrival instead of blank.
        $batchId = $this->numericId($request->query('batch_id'))
            ?? ($batches->first()['id'] ?? null);

        $streamId = $this->numericId($request->query('stream_id'));

        $rankable = Admission::query()
            ->with(['batch', 'streamApplied'])
            ->whereIn('status', ['pending', 'reviewed'])
            ->when($batchId !== null, fn (Builder $q) => $q->where('batch_id', $batchId))
            ->when($streamId !== null, fn (Builder $q) => $q->where('stream_applied_id', $streamId))
            ->get();

        /*
            Split by whether a percentage can be worked out at all.

            A null or zero total is a data-entry slip on a public form, and
            dividing by it would rank the applicant last - as though they had
            failed - rather than saying "unknown". Those applications are
            listed separately so the office can chase the missing marks
            instead of silently dropping them.
        */
        $ranked = $rankable
            ->filter(fn (Admission $a) => $a->merit_percentage !== null)
            ->sortByDesc(fn (Admission $a) => $a->merit_percentage)
            // Ties are common - whole numbers of marks out of 1100 collide
            // often - so the application number breaks them, which keeps the
            // order stable between loads instead of shuffling at random.
            ->values();

        $unranked = $rankable
            ->filter(fn (Admission $a) => $a->merit_percentage === null)
            ->sortBy('application_number')
            ->values();

        $this->persistRanks(
            $ranked
                ->map(fn (Admission $a, int $i) => ['id' => $a->id, 'merit_rank' => $i + 1])
                ->all()
        );

        return Inertia::render('Admin/Admissions/MeritList', [
            'applications' => $ranked->map(fn (Admission $a, int $i) => [
                'id' => $a->id,
                'application_number' => $a->application_number,
                'applicant_name' => $a->applicant_name,
                'father_name' => $a->father_name,
                'cnic_bform' => $a->cnic_bform,
                'merit_percentage' => $a->merit_percentage,
                'previous_marks_obtained' => $a->previous_marks_obtained,
                'previous_marks_total' => $a->previous_marks_total,
                'stream_name' => $a->streamApplied?->name,
                'batch_name' => $a->batch?->name,
                'merit_rank' => $i + 1,
                'status' => $a->status,
            ])->all(),
            'unranked' => $unranked->map(fn (Admission $a) => [
                'id' => $a->id,
                'application_number' => $a->application_number,
                'applicant_name' => $a->applicant_name,
                'cnic_bform' => $a->cnic_bform,
                'status' => $a->status,
            ])->all(),
            'batches' => $batches,
            'streams' => $this->streamOptions(),
            'selected_batch_id' => $batchId,
            'selected_stream_id' => $streamId,
        ]);
    }

    /**
     * Write the computed ranks back to the applications.
     *
     * Each row is saved individually rather than through a single bulk
     * update, because every rank differs; a CASE expression would be one
     * query but a great deal harder to read for no real gain at this size.
     *
     * @param  array<int, array{id:int, merit_rank:int}>  $updates
     */
    private function persistRanks(array $updates): void
    {
        foreach ($updates as $update) {
            Admission::query()
                ->whereKey($update['id'])
                ->update(['merit_rank' => $update['merit_rank']]);
        }
    }

    /**
     * Turn an accepted application into a student.
     *
     * A transaction, because this writes to three tables and a half-created
     * student is worse than none: an account with no profile cannot be found
     * on the roll, and an application marked enrolled with no student behind
     * it has lost its only link to the real person.
     *
     * The password is generated here and shown once in the flash message.
     * It is never emailed and never stored in plain text - the User model
     * hashes it on the way in, so the only copy the college will ever see is
     * the one on screen at the moment of creation.
     */
    public function convert(Admission $admission): RedirectResponse
    {
        $this->ensureStatus($admission, ['accepted']);

        // The status guard already refuses a second conversion, but a
        // double-submitted request can arrive with the status still cached
        // in the model. Checking the link directly closes that window.
        abort_if(
            $admission->enrolled_student_profile_id !== null,
            422,
            'This application has already been converted.'
        );

        $password = Str::random(12);

        $profile = DB::transaction(function () use ($admission, $password): StudentProfile {
            $user = User::create([
                'name' => $admission->applicant_name,
                'email' => $this->generateUniqueEmail($admission->applicant_name),
                'password' => $password,
                'role' => UserRole::Student,
                'is_active' => true,

                // The applicant may have given a number another account
                // already holds. Losing the phone is a small problem; a failed
                // insert on a full unique index would lose the whole
                // enrolment, so the phone is dropped instead.
                'phone' => $this->availablePhone($admission->phone),
            ]);

            $profile = StudentProfile::create([
                'user_id' => $user->id,
                'batch_id' => $admission->batch_id,

                // Assigned inside the transaction for the same reason the
                // students module does it: the number would otherwise be
                // stale, and the database is the only true reading.
                'roll_number' => StudentProfile::nextRollNumber($admission->batch_id),

                'cnic_bform' => $admission->cnic_bform,
                'father_name' => $admission->father_name,
                'date_of_birth' => $admission->date_of_birth,
                'guardian_phone' => $admission->guardian_phone,
                'address' => $admission->address,
                'previous_school' => $admission->previous_school,
                'previous_marks_obtained' => $admission->previous_marks_obtained,
                'previous_marks_total' => $admission->previous_marks_total,

                // The conversion date, not the date on the application: the
                // matric result may be a year old, but they start today.
                'admission_date' => now()->toDateString(),
                'status' => 'active',
            ]);

            /*
                Best-effort placement in a class.

                An accepted applicant should not have to be put in a class by
                hand before they can be marked enrolled, so the obvious
                target is used: a Grade 11 class in the active session, in
                the stream they applied for.

                A miss is not an error. Not every stream runs a Grade 11
                section in every session, and the office will place the
                student themselves; refusing to enrol somebody because no
                class matched would be worse than leaving them on the roll
                without one. There is deliberately no message about it - the
                admin can see the empty enrollment card on the student page
                and act on it.
            */
            $this->autoEnroll($profile, $admission);

            $admission->update([
                'status' => 'enrolled',
                'enrolled_student_profile_id' => $profile->id,
            ]);

            return $profile;
        });

        return redirect()
            ->route('admin.students.show', $profile->id)
            ->with(
                'success',
                "Student enrolled successfully. Login: {$profile->user->email} ".
                "Password: {$password} — This password is shown only once, please note it down."
            );
    }

    /**
     * Put a newly converted student into a class, if there is an obvious one.
     *
     * The target is a Grade 11 class in the active session, in the stream
     * the applicant applied for: admissions at this college are for new
     * Grade 11 students, so that is the only combination that is ever right.
     * Grade 12 transfers would need a different rule and a different entry
     * point.
     *
     * Only active classes are considered. A class left switched off is one
     * the office is not currently teaching, and quietly filling it would
     * put a student somewhere nobody would look for them.
     *
     * Silently does nothing when there is no match, and when the student
     * somehow already has an enrollment this session. The partial unique
     * index would reject the second insert; checking first keeps a manual
     * placement from being undone by a later conversion.
     */
    private function autoEnroll(StudentProfile $profile, Admission $admission): void
    {
        $session = AcademicSession::current();

        if ($session === null) {
            return;
        }

        $class = ClassModel::query()
            ->where('academic_session_id', $session->id)
            ->where('stream_id', $admission->stream_applied_id)
            ->where('grade_level', 11)
            ->where('is_active', true)
            ->orderBy('section')
            ->first();

        if ($class === null) {
            return;
        }

        $alreadyEnrolled = Enrollment::query()
            ->where('student_profile_id', $profile->id)
            ->where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->exists();

        if ($alreadyEnrolled) {
            return;
        }

        Enrollment::create([
            'student_profile_id' => $profile->id,
            'class_id' => $class->id,
            'academic_session_id' => $class->academic_session_id,
            'enrolled_at' => now()->toDateString(),
            'status' => 'active',
        ]);
    }

    /**
     * An email address for a converted student that nobody is using.
     *
     * Built from the applicant's own name so it is recognisable, with a
     * random number to make it unique - two "Ahmed Khan"s in one college is
     * not a rare thing.
     *
     * The collision check includes soft-deleted users, because the email
     * index on users is not partial: a deleted account still holds its
     * address, so ignoring them here would only defer the failure to an
     * insert later. Five attempts is far more than the name space needs; the
     * UUID fallback exists so a pathological case still enrols somebody
     * rather than throwing at the worst possible moment.
     */
    private function generateUniqueEmail(string $name): string
    {
        $slug = Str::limit(Str::slug($name), 20, '');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $email = $slug.'-'.random_int(1000, 9999).'@college.test';

            if (! User::withTrashed()->where('email', $email)->exists()) {
                return $email;
            }
        }

        return Str::uuid()->toString().'@college.test';
    }

    /**
     * The phone number to give the new account, or null if it is not free.
     */
    private function availablePhone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        return User::withTrashed()->where('phone', $phone)->exists() ? null : $phone;
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
