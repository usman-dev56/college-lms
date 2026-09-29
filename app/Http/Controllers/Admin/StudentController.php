<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStudentRequest;
use App\Http\Requests\Admin\UpdateStudentRequest;
use App\Models\StudentBatch;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    /**
     * How many students fit on one page.
     */
    private const PER_PAGE = 20;

    /**
     * The batch a new student is offered first, used for the roll number
     * preview on the create form.
     *
     * The newest active cohort: an admin admitting a student is admitting
     * them to the year group currently taking Grade 11, which is the newest
     * batch still marked active.
     */
    private function defaultBatchId(): ?int
    {
        return StudentBatch::query()
            ->active()
            ->orderByDesc('name')
            ->value('id');
    }

    /**
     * List students.
     *
     * Note on filters: a "class" filter is deliberately absent. The question
     * it would answer - which students sit in a given class - needs the
     * enrollments table, which arrives in sub-stage 3.4. Until then a class
     * has no students to filter, so the drop-down would be a filter that
     * silently returned the whole list.
     */
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $students = StudentProfile::query()
            ->with(['user', 'batch'])
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $query) use ($filters): void {
                    // ILIKE, not LIKE: PostgreSQL's LIKE is case-sensitive, so
                    // searching for "ahmed" would never find "Ahmed Khan". The
                    // term is bound rather than interpolated, and the orWhere
                    // stays inside this closure so it cannot escape the
                    // filters wrapped around it.
                    $term = '%'.$filters['search'].'%';

                    // Name and email live on the related user row, so those
                    // two need whereHas; the roll number and CNIC are columns
                    // on the profile itself.
                    $query->where('roll_number', 'ilike', $term)
                        ->orWhere('cnic_bform', 'ilike', $term)
                        ->orWhereHas('user', function (Builder $query) use ($term): void {
                            $query->where('name', 'ilike', $term)
                                ->orWhere('email', 'ilike', $term);
                        });
                });
            })
            ->when($filters['batch_id'] !== null, function (Builder $query) use ($filters): void {
                $query->where('batch_id', $filters['batch_id']);
            })
            ->when($filters['status'] !== 'all', function (Builder $query) use ($filters): void {
                $query->where('status', $filters['status']);
            })
            ->when($filters['gender'] !== 'all', function (Builder $query) use ($filters): void {
                $query->where('gender', $filters['gender']);
            })
            // Batch first, then roll number within it: the order a school
            // office reads a register in. Roll number is a string, so this
            // sorts lexicographically, which is correct for the zero-padded
            // form the generator produces.
            ->orderBy('batch_id')
            ->orderBy('roll_number')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (StudentProfile $student) => $this->studentSummary($student));

        return Inertia::render('Admin/Students/Index', [
            'students' => $students,
            'batches' => $this->batchOptions(),
            'filters' => $filters,
        ]);
    }

    /**
     * Show the create form.
     */
    public function create(): Response
    {
        $defaultBatchId = $this->defaultBatchId();

        return Inertia::render('Admin/Students/Create', [
            'batches' => $this->batchOptions(),
            'defaultBatchId' => $defaultBatchId,
            // A preview only. The real number is decided at save time, inside
            // the transaction, so two admins creating a student at the same
            // moment cannot both be handed the same number.
            'nextRollNumber' => $defaultBatchId === null
                ? null
                : StudentProfile::nextRollNumber($defaultBatchId),
        ]);
    }

    /**
     * Store a newly created student.
     *
     * Two rows, so the whole thing runs in a transaction: a user without a
     * profile (or the reverse) is not a state the college can be in, because
     * the student would be unable to log in *or* invisible in the register.
     *
     * The role is fixed here rather than read from the request, so a crafted
     * payload cannot create an administrator through this form. The password
     * is passed as typed because the User model casts it to hashed.
     */
    public function store(StoreStudentRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Read out here rather than inside the closure: the closure captures
        // $validated, not the request, and reaching for $request inside it
        // would be an undefined variable at save time.
        $userAttributes = $request->safe()->only(['name', 'email', 'phone', 'password', 'is_active']);

        $student = DB::transaction(function () use ($userAttributes, $validated): StudentProfile {
            $user = User::create([
                ...$userAttributes,
                'role' => UserRole::Student,
            ]);

            return StudentProfile::create([
                ...$this->profileAttributes($validated),

                'user_id' => $user->id,
                'batch_id' => $validated['batch_id'],

                // Assigned here, not on the form: a preview shown on the page
                // is already stale by the time the admin presses save, and two
                // admins saving at once would both be holding the same
                // preview. Asking the database inside the transaction is the
                // only reading that is actually true at insert time.
                'roll_number' => $this->resolveRollNumber(
                    $validated['roll_number'] ?? null,
                    (int) $validated['batch_id'],
                ),
            ]);
        });

        return redirect()
            ->route('admin.students.index')
            ->with('success', "Student {$student->roll_number} created successfully.");
    }

    /**
     * Show one student.
     *
     * The role guard runs before anything else is read, so an id belonging to
     * a teacher or an admin behaves as a missing record rather than exposing
     * that account through the students module.
     */
    public function show(StudentProfile $student): Response
    {
        $this->ensureStudent($student);

        $student->load(['user', 'batch']);

        return Inertia::render('Admin/Students/Show', [
            'student' => [
                'id' => $student->id,
                'name' => $student->user?->name,
                'email' => $student->user?->email,
                'phone' => $student->user?->phone,
                'is_active' => (bool) $student->user?->is_active,
                'roll_number' => $student->roll_number,
                'board_registration_number' => $student->board_registration_number,
                'cnic_bform' => $student->cnic_bform,
                'date_of_birth' => $student->date_of_birth?->toDateString(),
                'gender' => $student->gender,
                'father_name' => $student->father_name,
                'guardian_phone' => $student->guardian_phone,
                'address' => $student->address,
                'admission_date' => $student->admission_date?->toDateString(),
                'previous_school' => $student->previous_school,
                'previous_marks_obtained' => $student->previous_marks_obtained,
                'previous_marks_total' => $student->previous_marks_total,
                'status' => $student->status,
                'created_at' => $student->created_at?->toDateString(),
                'batch' => [
                    'id' => $student->batch?->id,
                    'name' => $student->batch?->name,
                    'start_grade' => $student->batch?->start_grade,
                    'current_grade' => $student->batch?->current_grade,
                ],
            ],

            // Enrollments arrive in sub-stage 3.4. The key is sent empty
            // rather than omitted so the page already has the shape it will
            // need once the relation exists.
            'enrollments' => [],
        ]);
    }

    /**
     * Show the edit form.
     *
     * The password is deliberately absent from the props.
     */
    public function edit(StudentProfile $student): Response
    {
        $this->ensureStudent($student);

        $student->load(['user', 'batch']);

        return Inertia::render('Admin/Students/Edit', [
            'student' => [
                'id' => $student->id,
                'name' => $student->user?->name,
                'email' => $student->user?->email,
                'phone' => $student->user?->phone,
                'is_active' => (bool) $student->user?->is_active,
                'batch_id' => $student->batch_id,
                'roll_number' => $student->roll_number,
                'board_registration_number' => $student->board_registration_number,
                'cnic_bform' => $student->cnic_bform,
                'date_of_birth' => $student->date_of_birth?->toDateString(),
                'gender' => $student->gender,
                'father_name' => $student->father_name,
                'guardian_phone' => $student->guardian_phone,
                'address' => $student->address,
                'admission_date' => $student->admission_date?->toDateString(),
                'previous_school' => $student->previous_school,
                'previous_marks_obtained' => $student->previous_marks_obtained,
                'previous_marks_total' => $student->previous_marks_total,
                'status' => $student->status,
            ],
            'batches' => $this->batchOptions(),
        ]);
    }

    /**
     * Update an existing student.
     *
     * A transaction again, for the same reason as store: the account and the
     * profile have to move together or not at all. The role is never touched
     * here - this module manages students, and changing someone's role is a
     * separate concern - and a blank password keeps the current one.
     */
    public function update(
        UpdateStudentRequest $request,
        StudentProfile $student
    ): RedirectResponse {
        $this->ensureStudent($student);

        $validated = $request->validated();

        DB::transaction(function () use ($request, $student, $validated): void {
            $userAttributes = $request->safe()->only(['name', 'email', 'phone', 'is_active']);

            // Only overwrite the password when a new one was actually typed.
            if ($request->filled('password')) {
                $userAttributes['password'] = $request->string('password')->value();
            }

            $student->user->update($userAttributes);

            $student->update([
                ...$this->profileAttributes($validated),
                'batch_id' => $validated['batch_id'],

                // Blank on the edit form means "leave the roll number alone"
                // rather than "generate a new one": the student already has a
                // number, and reassigning it would reorder a register that
                // has already been printed and handed out.
                'roll_number' => $this->resolveRollNumber(
                    $validated['roll_number'] ?? null,
                    (int) $validated['batch_id'],
                    $student,
                ),
            ]);
        });

        return redirect()
            ->route('admin.students.index')
            ->with('success', 'Student updated successfully.');
    }

    /**
     * Soft delete a student.
     *
     * Both rows go, together. Soft deleting only the profile would leave a
     * live login that leads nowhere: the account would still authenticate,
     * and the student dashboard would have nothing to show. Hard deleting
     * the user instead would take the record with it, and a withdrawn
     * student's history has to survive for the register. So both rows are
     * soft deleted in one transaction, which leaves them recoverable as a
     * pair - the foreign key is RESTRICT precisely so one cannot be removed
     * from under the other.
     */
    public function destroy(StudentProfile $student): RedirectResponse
    {
        $this->ensureStudent($student);

        DB::transaction(function () use ($student): void {
            $student->delete();
            $student->user?->delete();
        });

        return redirect()
            ->route('admin.students.index')
            ->with('success', 'Student deleted.');
    }

    /**
     * Refuse to act on a profile whose account is not a student.
     *
     * A 404 rather than a 403: the students module behaves as though
     * non-student accounts do not exist in it at all. The account is checked
     * rather than the profile's existence, because a profile whose user is
     * missing or already deleted is equally not something this page should
     * render.
     */
    private function ensureStudent(StudentProfile $student): void
    {
        abort_unless($student->user?->isStudent(), 404);
    }

    /**
     * The roll number to save: the one the admin typed, or the next free one.
     *
     * @param  StudentProfile|null  $current  The profile being updated, so its
     *                                        own number is not treated as a clash.
     */
    private function resolveRollNumber(
        ?string $rollNumber,
        int $batchId,
        ?StudentProfile $current = null
    ): string {
        if ($rollNumber !== null && $rollNumber !== '') {
            return $rollNumber;
        }

        // On update, an untouched roll number keeps whatever the student
        // already has. Only a genuinely new student is given the next one.
        if ($current !== null) {
            return $current->roll_number;
        }

        return StudentProfile::nextRollNumber($batchId);
    }

    /**
     * The validated profile columns, with the status defaulted.
     *
     * Split out of store and update because both write the same columns, and
     * keeping the list in one place is what stops the two methods drifting
     * apart - a field added to one form and forgotten in the other is the
     * usual way a profile ends up half-saved.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function profileAttributes(array $validated): array
    {
        $columns = array_flip([
            'board_registration_number',
            'cnic_bform',
            'date_of_birth',
            'gender',
            'father_name',
            'guardian_phone',
            'address',
            'admission_date',
            'previous_school',
            'previous_marks_obtained',
            'previous_marks_total',
        ]);

        return [
            ...array_intersect_key($validated, $columns),

            // Absent from the form means "active", not null: the column is
            // NOT NULL with a default, and an explicit null would fail.
            'status' => $validated['status'] ?? 'active',
        ];
    }

    /**
     * A student as the list needs them.
     *
     * @return array<string, mixed>
     */
    private function studentSummary(StudentProfile $student): array
    {
        return [
            'id' => $student->id,
            'name' => $student->user?->name,
            'email' => $student->user?->email,
            'phone' => $student->user?->phone,
            'roll_number' => $student->roll_number,
            'batch_id' => $student->batch_id,
            'batch_name' => $student->batch?->name,
            'gender' => $student->gender,
            'status' => $student->status,
            'is_active' => (bool) $student->user?->is_active,

            // Always null in 3.2. A student's class comes from their
            // enrollments, which arrive in 3.4; until then the key is sent as
            // null so the table already has the shape it will need.
            'enrolled_class' => null,
        ];
    }

    /**
     * Batches for the filter and form drop-downs.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function batchOptions(): Collection
    {
        return StudentBatch::query()
            ->orderByDesc('is_active')
            ->orderByDesc('name')
            ->get()
            ->map(fn (StudentBatch $batch) => [
                'id' => $batch->id,
                'name' => $batch->name,
                'is_active' => $batch->is_active,
                'current_grade' => $batch->current_grade,
            ]);
    }

    /**
     * The filter values from the query string, normalised for the form.
     *
     * Ids are cast only when they are numeric, so a hand-edited URL cannot
     * turn a filter into a query against an unexpected value. The two
     * enum-like strings are checked against their allowed values and fall
     * back to 'all' rather than being passed through unfiltered.
     *
     * @return array{search:string, batch_id:int|null, status:string, gender:string}
     */
    private function filters(Request $request): array
    {
        $status = (string) $request->query('status', 'all');
        $gender = (string) $request->query('gender', 'all');

        return [
            'search' => trim((string) $request->query('search', '')),
            'batch_id' => $this->numericId($request->query('batch_id')),
            'status' => in_array($status, ['active', 'graduated', 'withdrawn', 'suspended'], true)
                ? $status
                : 'all',
            'gender' => in_array($gender, ['male', 'female', 'other'], true)
                ? $gender
                : 'all',
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
