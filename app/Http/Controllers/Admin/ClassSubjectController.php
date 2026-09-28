<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateClassSubjectsRequest;
use App\Models\ClassModel;
use App\Models\ClassSubject;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ClassSubjectController extends Controller
{
    /**
     * Show the assignment form for a class.
     *
     * One row is rendered per subject that can be taught in this class: the
     * compulsory subjects of its grade, plus the electives of its stream.
     * Existing assignments are passed separately so the form can be
     * pre-filled rather than rebuilt from scratch on every visit.
     */
    public function edit(ClassModel $class): Response
    {
        $class->loadMissing('stream');

        return Inertia::render('Admin/Classes/Assignments', [
            'class' => [
                'id' => $class->id,
                'display_name' => $class->displayName(),
                'grade_level' => $class->grade_level,
                'stream' => [
                    'id' => $class->stream->id,
                    'name' => $class->stream->name,
                    'code' => $class->stream->code,
                ],
            ],
            'subjects' => $this->availableSubjects($class),
            'teachers' => $this->activeTeachers(),
            'existingAssignments' => ClassSubject::query()
                ->where('class_id', $class->id)
                ->orderBy('subject_id')
                ->get()
                ->map(fn (ClassSubject $assignment) => [
                    'subject_id' => $assignment->subject_id,
                    'teacher_id' => $assignment->teacher_id,
                    'periods_per_week' => $assignment->periods_per_week,
                ]),
        ]);
    }

    /**
     * Replace the teaching assignments of a class.
     *
     * The form submits the subjects that are being kept, so anything missing
     * from the payload is soft deleted rather than hard deleted: the history
     * stays on record and a subject added back later is restored instead of
     * duplicated. The whole change runs in one transaction so a failure
     * cannot leave a class half-assigned.
     */
    public function update(
        UpdateClassSubjectsRequest $request,
        ClassModel $class
    ): RedirectResponse {
        $assignments = $request->validated()['assignments'];

        $outsideClass = $this->subjectsOutsideClass($class, $assignments);

        if ($outsideClass->isNotEmpty()) {
            return back()
                ->withInput()
                ->withErrors([
                    'assignments' => 'One or more selected subjects are not taught in this class.',
                ]);
        }

        DB::transaction(function () use ($class, $assignments): void {
            $keptSubjectIds = collect($assignments)
                ->pluck('subject_id')
                ->all();

            ClassSubject::withTrashed()
                ->where('class_id', $class->id)
                ->whereNotIn('subject_id', $keptSubjectIds)
                ->get()
                ->each(function (ClassSubject $assignment): void {
                    if (! $assignment->trashed()) {
                        $assignment->delete();
                    }
                });

            foreach ($assignments as $assignment) {
                $row = ClassSubject::withTrashed()->updateOrCreate(
                    [
                        'class_id' => $class->id,
                        'subject_id' => $assignment['subject_id'],
                    ],
                    [
                        'teacher_id' => $assignment['teacher_id'],
                        'periods_per_week' => $assignment['periods_per_week'],
                    ]
                );

                if ($row->trashed()) {
                    $row->restore();
                }
            }
        });

        return redirect()
            ->route('admin.classes.assignments.edit', $class)
            ->with('success', 'Subject assignments updated successfully.');
    }

    /**
     * Subjects that can be taught in this class, compulsory ones first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function availableSubjects(ClassModel $class): Collection
    {
        return $this->availableSubjectsQuery($class)
            ->with('stream')
            ->orderByRaw('stream_id IS NULL DESC, name ASC')
            ->get()
            ->map(fn (Subject $subject) => [
                'id' => $subject->id,
                'name' => $subject->name,
                'code' => $subject->code,
                'stream_id' => $subject->stream_id,
                'stream_name' => $subject->stream?->name,
                'has_practical' => $subject->has_practical,
            ]);
    }

    /**
     * The subjects a class may be taught: the compulsory subjects of its
     * grade level, plus the electives of its stream.
     */
    private function availableSubjectsQuery(ClassModel $class): Builder
    {
        return Subject::query()
            ->active()
            ->where('grade_level', $class->grade_level)
            ->where(function (Builder $query) use ($class): void {
                $query->whereNull('stream_id')
                    ->orWhere('stream_id', $class->stream_id);
            });
    }

    /**
     * The active teachers that can be assigned to a class.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function activeTeachers(): Collection
    {
        return User::query()
            ->where('role', UserRole::Teacher->value)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (User $teacher) => [
                'id' => $teacher->id,
                'name' => $teacher->name,
                'email' => $teacher->email,
            ]);
    }

    /**
     * The submitted subjects that do not belong to this class.
     *
     * The per-row rules prove a subject exists, but not that it is taught in
     * this particular grade and stream, so that is checked before anything is
     * written.
     *
     * @param  array<int, array<string, mixed>>  $assignments
     * @return Collection<int, int>
     */
    private function subjectsOutsideClass(ClassModel $class, array $assignments): Collection
    {
        $subjectIds = collect($assignments)
            ->pluck('subject_id')
            ->unique()
            ->values();

        if ($subjectIds->isEmpty()) {
            return collect();
        }

        $allowedIds = $this->availableSubjectsQuery($class)
            ->whereIn('id', $subjectIds)
            ->pluck('id');

        return $subjectIds->diff($allowedIds)->values();
    }
}
