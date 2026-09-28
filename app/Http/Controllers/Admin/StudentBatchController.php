<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStudentBatchRequest;
use App\Http\Requests\Admin\UpdateStudentBatchRequest;
use App\Models\StudentBatch;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StudentBatchController extends Controller
{
    /**
     * List the batches, active ones first and then newest cohort first.
     *
     * The status filter is applied in the browser, the same way the streams,
     * subjects and classes lists do it: a batch list is short enough that
     * round-tripping it to the server on every filter change buys nothing.
     */
    public function index(): Response
    {
        $batches = StudentBatch::query()
            ->orderByDesc('is_active')
            ->orderByDesc('name')
            ->get()
            ->map(fn (StudentBatch $batch) => [
                'id' => $batch->id,
                'name' => $batch->name,
                'start_grade' => $batch->start_grade,
                'expected_graduation_year' => $batch->expected_graduation_year,
                'is_active' => $batch->is_active,
                'notes' => $batch->notes,
                // Reserved for sub-stage 3.2, where each batch counts the
                // student profiles on it.
                'students_count' => 0,
                'current_grade' => $batch->current_grade,
            ]);

        return Inertia::render('Admin/StudentBatches/Index', [
            'batches' => $batches,
        ]);
    }

    /**
     * Show the create form.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/StudentBatches/Create');
    }

    /**
     * Store a newly created student batch.
     */
    public function store(StoreStudentBatchRequest $request): RedirectResponse
    {
        StudentBatch::create($request->validated());

        return redirect()
            ->route('admin.student-batches.index')
            ->with('success', 'Student batch created successfully.');
    }

    /**
     * Show the edit form.
     */
    public function edit(StudentBatch $studentBatch): Response
    {
        return Inertia::render('Admin/StudentBatches/Edit', [
            'batch' => [
                'id' => $studentBatch->id,
                'name' => $studentBatch->name,
                'start_grade' => $studentBatch->start_grade,
                'expected_graduation_year' => $studentBatch->expected_graduation_year,
                'is_active' => $studentBatch->is_active,
                'notes' => $studentBatch->notes,
            ],
        ]);
    }

    /**
     * Update an existing student batch.
     */
    public function update(
        UpdateStudentBatchRequest $request,
        StudentBatch $studentBatch
    ): RedirectResponse {
        $studentBatch->update($request->validated());

        return redirect()
            ->route('admin.student-batches.index')
            ->with('success', 'Student batch updated successfully.');
    }

    /**
     * Soft delete a batch.
     *
     * A deleted batch frees its name for reuse, because the unique index on
     * the table is partial on deleted_at IS NULL. Batches have no
     * single-active rule, so one is deactivated with a normal edit.
     *
     * Sub-stage 3.2 gives batches students. Until then method_exists() is
     * false and there is nothing to block on; once the relationship lands,
     * deleting a batch that still has students on it is refused, the same
     * shape as the teachers module refusing to delete a teacher who still
     * holds assignments.
     */
    public function destroy(StudentBatch $studentBatch): RedirectResponse
    {
        if (
            method_exists($studentBatch, 'studentProfiles') &&
            $studentBatch->studentProfiles()->exists()
        ) {
            return redirect()
                ->route('admin.student-batches.index')
                ->with('error', 'Cannot delete a batch that still has students.');
        }

        $studentBatch->delete();

        return redirect()
            ->route('admin.student-batches.index')
            ->with('success', 'Student batch deleted.');
    }
}
