<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreClassRequest;
use App\Http\Requests\Admin\UpdateClassRequest;
use App\Models\AcademicSession;
use App\Models\ClassModel;
use App\Models\Stream;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ClassController extends Controller
{
    /**
     * List all classes: newest session first, then grade level, stream name
     * and section.
     *
     * The join on streams is only there to sort by stream name; the
     * relationships themselves are eager loaded for the display name.
     */
    public function index(): Response
    {
        $classes = ClassModel::query()
            ->with(['academicSession', 'stream'])
            ->join('streams', 'streams.id', '=', 'classes.stream_id')
            ->select('classes.*')
            ->orderByDesc('classes.academic_session_id')
            ->orderBy('classes.grade_level')
            ->orderBy('streams.name')
            ->orderBy('classes.section')
            ->get()
            ->map(fn (ClassModel $class) => [
                'id' => $class->id,
                'display_name' => $class->displayName(),
                'academic_session_id' => $class->academic_session_id,
                'academic_session' => $class->academicSession?->name,
                'stream_id' => $class->stream_id,
                'stream' => $class->stream?->name,
                'grade_level' => $class->grade_level,
                'section' => $class->section,
                'capacity' => $class->capacity,
                'room' => $class->room,
                'is_active' => $class->is_active,
            ]);

        return Inertia::render('Admin/Classes/Index', [
            'classes' => $classes,
            'sessions' => $this->sessionOptions(),
            'streams' => $this->streamOptions(),
        ]);
    }

    /**
     * Show the create form.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Classes/Create', [
            'sessions' => $this->sessionOptions(),
            'streams' => $this->streamOptions(),
        ]);
    }

    /**
     * Store a newly created class.
     */
    public function store(StoreClassRequest $request): RedirectResponse
    {
        ClassModel::create($request->validated());

        return redirect()
            ->route('admin.classes.index')
            ->with('success', 'Class created successfully.');
    }

    /**
     * Show the edit form.
     */
    public function edit(ClassModel $class): Response
    {
        return Inertia::render('Admin/Classes/Edit', [
            'class' => [
                'id' => $class->id,
                'academic_session_id' => $class->academic_session_id,
                'stream_id' => $class->stream_id,
                'grade_level' => $class->grade_level,
                'section' => $class->section,
                'capacity' => $class->capacity,
                'room' => $class->room,
                'is_active' => $class->is_active,
            ],
            'sessions' => $this->sessionOptions(),
            'streams' => $this->streamOptions(),
        ]);
    }

    /**
     * Update an existing class.
     */
    public function update(
        UpdateClassRequest $request,
        ClassModel $class
    ): RedirectResponse {
        $class->update($request->validated());

        return redirect()
            ->route('admin.classes.index')
            ->with('success', 'Class updated successfully.');
    }

    /**
     * Delete a class.
     *
     * Classes are soft deleted, which frees the session/grade/stream/section
     * combination for reuse (the unique index is partial on deleted_at).
     * Dependent checks belong here once enrolments, attendance and
     * timetables reference classes.
     */
    public function destroy(ClassModel $class): RedirectResponse
    {
        $class->delete();

        return redirect()
            ->route('admin.classes.index')
            ->with('success', 'Class deleted.');
    }

    /**
     * Academic sessions for the drop-downs, newest first.
     *
     * Soft-deleted sessions are left out because they can no longer be
     * assigned to a class.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function sessionOptions(): Collection
    {
        return AcademicSession::query()
            ->orderByDesc('name')
            ->get()
            ->map(fn (AcademicSession $session) => [
                'id' => $session->id,
                'name' => $session->name,
                'is_active' => $session->is_active,
            ]);
    }

    /**
     * Streams for the drop-downs.
     *
     * Inactive streams are included so that classes pointing at one stay
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
}
