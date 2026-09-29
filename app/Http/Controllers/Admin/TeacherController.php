<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTeacherRequest;
use App\Http\Requests\Admin\UpdateTeacherRequest;
use App\Models\ClassModel;
use App\Models\ClassSubject;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class TeacherController extends Controller
{
    /**
     * How many teachers fit on one page.
     */
    private const PER_PAGE = 20;

    /**
     * List teachers.
     *
     * The subject and class filters select teachers who actually hold an
     * assignment for that subject or class, which is the question an admin is
     * really asking ("who teaches Physics?") rather than a text match.
     */
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $teachers = User::query()
            ->where('role', UserRole::Teacher->value)
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $query) use ($filters): void {
                    // ILIKE, not LIKE: PostgreSQL's LIKE is case-sensitive,
                    // so searching for "ahmed" would never find "Ahmed Khan".
                    // The term is bound rather than interpolated, and the
                    // orWhere stays inside this closure so it cannot escape
                    // the filters wrapped around it.
                    $term = '%'.$filters['search'].'%';

                    $query->where('name', 'ilike', $term)
                        ->orWhere('email', 'ilike', $term);
                });
            })
            ->when($filters['subject_id'] !== null, function (Builder $query) use ($filters): void {
                $query->whereHas('teacherAssignments', function (Builder $query) use ($filters): void {
                    $query->where('subject_id', $filters['subject_id']);
                });
            })
            ->when($filters['class_id'] !== null, function (Builder $query) use ($filters): void {
                $query->whereHas('teacherAssignments', function (Builder $query) use ($filters): void {
                    $query->where('class_id', $filters['class_id']);
                });
            })
            ->when($filters['status'] !== 'all', function (Builder $query) use ($filters): void {
                $query->where('is_active', $filters['status'] === 'active');
            })
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (User $teacher) => $this->teacherSummary($teacher));

        return Inertia::render('Admin/Teachers/Index', [
            'teachers' => $teachers,
            'subjects' => $this->subjectOptions(),
            'classes' => $this->classOptions(),
            'filters' => $filters,
        ]);
    }

    /**
     * Show the create form.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Teachers/Create');
    }

    /**
     * Store a newly created teacher.
     *
     * The role is fixed here rather than read from the request, so a crafted
     * payload cannot create an administrator through this form. The password
     * is passed as typed because the User model casts it to hashed.
     */
    public function store(StoreTeacherRequest $request): RedirectResponse
    {
        User::create([
            ...$request->safe()->only(['name', 'email', 'phone', 'password', 'is_active']),
            'role' => UserRole::Teacher,
        ]);

        return redirect()
            ->route('admin.teachers.index')
            ->with('success', 'Teacher created successfully.');
    }

    /**
     * Show one teacher with the classes and subjects they teach.
     *
     * The role guard runs before anything is loaded, so an admin id in the URL
     * behaves as a missing record rather than exposing another role's data.
     */
    public function show(User $teacher): Response
    {
        $this->ensureTeacher($teacher);

        $assignments = $teacher->teacherAssignments()
            ->with(['classModel.stream', 'subject'])
            ->get()
            ->map(fn (ClassSubject $assignment) => [
                'id' => $assignment->id,
                'class_id' => $assignment->classModel->id,
                'class_display_name' => $assignment->classModel->displayName(),
                'grade_level' => $assignment->classModel->grade_level,
                'stream_name' => $assignment->classModel->stream?->name,
                'section' => $assignment->classModel->section,
                'subject_id' => $assignment->subject->id,
                'subject_name' => $assignment->subject->name,
                'subject_code' => $assignment->subject->code,
                'periods_per_week' => $assignment->periods_per_week,
            ])
            ->sortBy([
                fn (array $a, array $b) => $a['grade_level'] <=> $b['grade_level'],
                fn (array $a, array $b) => $a['class_display_name'] <=> $b['class_display_name'],
                fn (array $a, array $b) => $a['subject_name'] <=> $b['subject_name'],
            ])
            ->values();

        return Inertia::render('Admin/Teachers/Show', [
            'teacher' => [
                'id' => $teacher->id,
                'name' => $teacher->name,
                'email' => $teacher->email,
                'phone' => $teacher->phone,
                'is_active' => $teacher->is_active,
                'created_at' => $teacher->created_at?->toDateString(),
            ],
            'assignments' => $assignments,
        ]);
    }

    /**
     * Show the edit form.
     *
     * The password is deliberately absent from the props.
     */
    public function edit(User $teacher): Response
    {
        $this->ensureTeacher($teacher);

        return Inertia::render('Admin/Teachers/Edit', [
            'teacher' => [
                'id' => $teacher->id,
                'name' => $teacher->name,
                'email' => $teacher->email,
                'phone' => $teacher->phone,
                'is_active' => $teacher->is_active,
            ],
        ]);
    }

    /**
     * Update an existing teacher.
     *
     * The role is never touched: this module manages teachers, and changing a
     * role is a separate concern. A blank password keeps the current one.
     */
    public function update(UpdateTeacherRequest $request, User $teacher): RedirectResponse
    {
        $this->ensureTeacher($teacher);

        $attributes = $request->safe()->only(['name', 'email', 'phone', 'is_active']);

        // Only overwrite the password when a new one was actually typed.
        if ($request->filled('password')) {
            $attributes['password'] = $request->string('password')->value();
        }

        $teacher->update($attributes);

        return redirect()
            ->route('admin.teachers.index')
            ->with('success', 'Teacher updated successfully.');
    }

    /**
     * Soft delete a teacher.
     *
     * A teacher who still holds assignments cannot be removed: the class
     * would be left without a teacher, so the admin is told to clear the
     * assignments first instead of losing the link.
     */
    public function destroy(User $teacher): RedirectResponse
    {
        $this->ensureTeacher($teacher);

        $assignmentCount = $teacher->teacherAssignments()->count();

        if ($assignmentCount > 0) {
            return back()->with(
                'error',
                "This teacher has {$assignmentCount} class assignment(s). "
                .'Remove them from classes before deleting.'
            );
        }

        $teacher->delete();

        return redirect()
            ->route('admin.teachers.index')
            ->with('success', 'Teacher deleted.');
    }

    /**
     * Refuse to act on a user who is not a teacher.
     *
     * A 404 rather than a 403: the teachers module behaves as though
     * non-teacher accounts do not exist in it at all.
     */
    private function ensureTeacher(User $teacher): void
    {
        abort_unless($teacher->role === UserRole::Teacher, 404);
    }

    /**
     * The filter values from the query string, normalised for the form.
     *
     * Ids are cast only when they are numeric, so a hand-edited URL cannot
     * turn a filter into a query against an unexpected value.
     *
     * @return array{search:string, subject_id:int|null, class_id:int|null, status:string}
     */
    private function filters(Request $request): array
    {
        $status = (string) $request->query('status', 'all');

        return [
            'search' => trim((string) $request->query('search', '')),
            'subject_id' => $this->numericId($request->query('subject_id')),
            'class_id' => $this->numericId($request->query('class_id')),
            'status' => in_array($status, ['active', 'inactive'], true) ? $status : 'all',
        ];
    }

    /**
     * A teacher as the list needs them: contact details, the subjects they
     * teach, and how many classes they cover.
     *
     * @return array<string, mixed>
     */
    private function teacherSummary(User $teacher): array
    {
        return [
            'id' => $teacher->id,
            'name' => $teacher->name,
            'email' => $teacher->email,
            'phone' => $teacher->phone,
            'is_active' => $teacher->is_active,
            'subjects' => $this->subjectNamesFor($teacher->id),
            'assignments_count' => ClassSubject::query()
                ->where('teacher_id', $teacher->id)
                ->count(),
        ];
    }

    /**
     * The distinct subject names one teacher is assigned to teach.
     *
     * @return array<int, string>
     */
    private function subjectNamesFor(int $teacherId): array
    {
        // The subjects table holds a separate row per grade level, so a
        // teacher who takes English in grade 11 and grade 12 matches two rows
        // with the same name. DISTINCT collapses them into one entry, which
        // is what the list column is for.
        return Subject::query()
            ->whereIn('id', ClassSubject::query()
                ->where('teacher_id', $teacherId)
                ->select('subject_id'))
            ->distinct()
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    /**
     * Subjects for the filter drop-downs.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function subjectOptions(): Collection
    {
        return Subject::query()
            ->active()
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get()
            ->map(fn (Subject $subject) => [
                'id' => $subject->id,
                'name' => $subject->name,
                'code' => $subject->code,
                'grade_level' => $subject->grade_level,
            ]);
    }

    /**
     * Classes for the filter drop-downs.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function classOptions(): Collection
    {
        return ClassModel::query()
            ->with('stream')
            ->orderByDesc('academic_session_id')
            ->orderBy('grade_level')
            ->orderBy('section')
            ->get()
            ->map(fn (ClassModel $class) => [
                'id' => $class->id,
                'display_name' => $class->displayName(),
                'grade_level' => $class->grade_level,
                'stream_name' => $class->stream?->name,
                'section' => $class->section,
            ]);
    }

    /**
     * Read a positive integer id from a query value, or null.
     */
    private function numericId(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
