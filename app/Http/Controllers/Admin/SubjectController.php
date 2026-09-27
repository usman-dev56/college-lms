<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSubjectRequest;
use App\Http\Requests\Admin\UpdateSubjectRequest;
use App\Models\Stream;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class SubjectController extends Controller
{
    /**
     * List all subjects: grade level first, compulsory before electives,
     * then alphabetical by name.
     */
    public function index(): Response
    {
        $subjects = Subject::query()
            ->with('stream')
            ->orderBy('grade_level')
            ->orderByRaw('stream_id asc nulls first')
            ->orderBy('name')
            ->get()
            ->map(fn (Subject $subject) => [
                'id' => $subject->id,
                'name' => $subject->name,
                'code' => $subject->code,
                'grade_level' => $subject->grade_level,
                'stream_id' => $subject->stream_id,
                'stream' => $subject->stream?->name,
                'has_practical' => $subject->has_practical,
                'is_active' => $subject->is_active,
            ]);

        return Inertia::render('Admin/Subjects/Index', [
            'subjects' => $subjects,
            'streams' => $this->streamOptions(),
        ]);
    }

    /**
     * Show the create form.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Subjects/Create', [
            'streams' => $this->streamOptions(),
        ]);
    }

    /**
     * Store a newly created subject.
     */
    public function store(StoreSubjectRequest $request): RedirectResponse
    {
        $subject = Subject::create($request->validated());

        $this->syncPrimaryStream($subject);

        return redirect()
            ->route('admin.subjects.index')
            ->with('success', 'Subject created successfully.');
    }

    /**
     * Show the edit form.
     */
    public function edit(Subject $subject): Response
    {
        return Inertia::render('Admin/Subjects/Edit', [
            'subject' => [
                'id' => $subject->id,
                'name' => $subject->name,
                'code' => $subject->code,
                'grade_level' => $subject->grade_level,
                'stream_id' => $subject->stream_id,
                'has_practical' => $subject->has_practical,
                'is_active' => $subject->is_active,
            ],
            'streams' => $this->streamOptions(),
        ]);
    }

    /**
     * Update an existing subject.
     */
    public function update(
        UpdateSubjectRequest $request,
        Subject $subject
    ): RedirectResponse {
        $subject->update($request->validated());

        $this->syncPrimaryStream($subject);

        return redirect()
            ->route('admin.subjects.index')
            ->with('success', 'Subject updated successfully.');
    }

    /**
     * Delete a subject.
     *
     * Subjects are soft deleted. The stream_subject links are kept so that
     * restoring a subject later also restores its stream links. Dependent
     * checks belong here once marks and attendance reference subjects.
     */
    public function destroy(Subject $subject): RedirectResponse
    {
        $subject->delete();

        return redirect()
            ->route('admin.subjects.index')
            ->with('success', 'Subject deleted.');
    }

    /**
     * Streams for the drop-downs.
     *
     * Inactive streams are included so that subjects pointing at one stay
     * editable; the UI marks them as inactive.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function streamOptions(): Collection
    {
        return Stream::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Stream $stream) => [
                'id' => $stream->id,
                'name' => $stream->name,
                'code' => $stream->code,
                'is_active' => $stream->is_active,
            ]);
    }

    /**
     * Keep the stream_subject pivot in step with the subject's primary
     * stream: an elective subject is always linked to the stream stored in
     * stream_id, and a compulsory subject has no stream links at all.
     */
    private function syncPrimaryStream(Subject $subject): void
    {
        if ($subject->stream_id === null) {
            $subject->streams()->sync([]);

            return;
        }

        $subject->streams()->syncWithoutDetaching([$subject->stream_id]);
    }
}
