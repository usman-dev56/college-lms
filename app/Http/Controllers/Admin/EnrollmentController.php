<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkUnenrollRequest;
use App\Http\Requests\Admin\StoreEnrollmentRequest;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\StudentProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EnrollmentController extends Controller
{
    /**
     * The class roster: who is in this class, and who could be added.
     */
    public function index(ClassModel $class): Response
    {
        $class->load(['stream', 'academicSession']);

        /*
            Scoped to the class's own session, not to whichever session is
            active right now. A class belongs to exactly one session, and a
            roster for a class from last year is a historical document - the
            active session is a different year entirely and would show
            nothing.
        */
        $enrolled = Enrollment::query()
            ->where('class_id', $class->id)
            ->where('academic_session_id', $class->academic_session_id)
            ->where('status', 'active')
            ->with(['studentProfile.user', 'studentProfile.batch'])
            ->get()
            ->map(fn (Enrollment $enrollment) => [
                'enrollment_id' => $enrollment->id,
                'student_profile_id' => $enrollment->student_profile_id,
                'roll_number' => $enrollment->studentProfile?->roll_number,
                'name' => $enrollment->studentProfile?->user?->name,
                'email' => $enrollment->studentProfile?->user?->email,
                'phone' => $enrollment->studentProfile?->user?->phone,
                'gender' => $enrollment->studentProfile?->gender,
                'batch_name' => $enrollment->studentProfile?->batch?->name,
                'status' => $enrollment->status,
            ])
            ->sortBy('roll_number')
            ->values();

        /*
            The candidates: active students with no live enrollment in this
            class's session.

            Scoped by the session rather than by batch or grade on purpose.
            A batch is a cohort and a session is a year, and the two only
            coincide in the year a cohort starts; matching on either would
            offer a Grade 12 student to a Grade 11 class in the year they are
            promoted, or hide a student whose batch and grade have drifted
            apart. What the office actually needs is "everybody who could
            still be put in this class", and the session is the honest
            boundary for that.
        */
        $unassigned = StudentProfile::query()
            ->whereHas('user', fn ($query) => $query->where('is_active', true))
            ->whereDoesntHave('enrollments', fn ($query) => $query
                ->where('academic_session_id', $class->academic_session_id)
                ->where('status', 'active'))
            ->where('status', 'active')
            ->with(['user', 'batch'])
            ->orderBy('roll_number')
            ->get()
            ->map(fn (StudentProfile $student) => [
                'id' => $student->id,
                'roll_number' => $student->roll_number,
                'name' => $student->user?->name,
                'email' => $student->user?->email,
                'batch_name' => $student->batch?->name,
            ]);

        return Inertia::render('Admin/Enrollments/ClassRoster', [
            'class' => [
                'id' => $class->id,
                'display_name' => $class->displayName(),
                'grade_level' => $class->grade_level,
                'stream_name' => $class->stream?->name,
                'section' => $class->section,
                'room' => $class->room,
                'session_name' => $class->academicSession?->name,
            ],
            'students' => $enrolled,
            'unassignedStudents' => $unassigned,
        ]);
    }

    /**
     * Enroll a set of students into a class.
     *
     * Skipping rather than failing is the point: the office works down a
     * list of a whole section, and one student who is already in this
     * session should not block the other forty. The partial unique index on
     * (student, session) is what makes the skip necessary, so the check
     * mirrors the index rather than relying on the insert to fail.
     *
     * Everything runs in one transaction: a half-enrolled section is a
     * state the office would have to unpick by hand.
     */
    public function store(StoreEnrollmentRequest $request): RedirectResponse
    {
        $class = ClassModel::query()->findOrFail($request->validated()['class_id']);

        $enrolled = 0;
        $skipped = 0;

        DB::transaction(function () use ($request, $class, &$enrolled, &$skipped): void {
            foreach ($request->validated()['student_profile_ids'] as $studentProfileId) {
                $alreadyEnrolled = Enrollment::query()
                    ->where('student_profile_id', $studentProfileId)
                    ->where('academic_session_id', $class->academic_session_id)
                    ->where('status', 'active')
                    ->exists();

                if ($alreadyEnrolled) {
                    $skipped++;

                    continue;
                }

                Enrollment::create([
                    'student_profile_id' => $studentProfileId,
                    'class_id' => $class->id,

                    // Taken from the class, never from the request: a class
                    // belongs to exactly one session, and the request has no
                    // business overriding that.
                    'academic_session_id' => $class->academic_session_id,
                    'enrolled_at' => now()->toDateString(),
                    'status' => 'active',
                ]);

                $enrolled++;
            }
        });

        if ($enrolled === 0) {
            return back()->with(
                'error',
                "No students were enrolled. {$skipped} were already in a class this session."
            );
        }

        $message = "Enrolled {$enrolled} student(s).";

        if ($skipped > 0) {
            $message .= " {$skipped} skipped because they were already enrolled.";
        }

        return back()->with('success', $message);
    }

    /**
     * Remove one student from a class.
     *
     * The status is set before the soft delete so the row that survives in
     * the history says why it ended rather than just vanishing. Soft
     * deleting alone would free the unique index but leave the status
     * reading 'active' on a row nothing will ever look at again.
     */
    public function destroy(Enrollment $enrollment): RedirectResponse
    {
        abort_unless($enrollment->status === 'active', 422, 'This enrollment is not active.');

        $enrollment->update(['status' => 'withdrawn']);
        $enrollment->delete();

        return back()->with('success', 'Student removed from the class.');
    }

    /**
     * Remove several students from their classes at once.
     *
     * Each row is handled independently: a stale id in the middle of the list
     * should not abandon the rest. Ids that are not already withdrawn are
     * counted so the flash message can say what actually happened.
     */
    public function bulkUnenroll(BulkUnenrollRequest $request): RedirectResponse
    {
        $removed = 0;

        DB::transaction(function () use ($request, &$removed): void {
            $enrollments = Enrollment::query()
                ->whereIn('id', $request->validated()['enrollment_ids'])
                ->get();

            foreach ($enrollments as $enrollment) {
                if ($enrollment->status !== 'active') {
                    continue;
                }

                $enrollment->update(['status' => 'withdrawn']);
                $enrollment->delete();
                $removed++;
            }
        });

        if ($removed === 0) {
            return back()->with('error', 'None of the selected enrollments were active.');
        }

        return back()->with('success', "Removed {$removed} student(s) from their class.");
    }

    /**
     * Every class this student has been enrolled in, across all sessions.
     *
     * Newest session first, because the most recent one is the one being
     * asked about. A soft-deleted or withdrawn row is kept in the list: the
     * point of a history is that it shows where somebody used to be, and
     * dropping those rows would make a student who moved class look as
     * though they had never been anywhere.
     */
    public function studentHistory(StudentProfile $student): Response
    {
        $this->ensureStudent($student);

        $student->load(['user', 'batch']);

        $enrollments = $student->enrollments()
            ->with(['classModel.stream', 'academicSession'])
            // withTrashed, because the soft-deleted rows are the history.
            ->withTrashed()
            ->get()
            ->sortByDesc(fn (Enrollment $enrollment) => $enrollment->academicSession?->name ?? '')
            ->values();

        // Which one is "current" is a question about the active session, not
        // about the newest row in the list: a class from last year is not
        // current just because nothing has replaced it yet.
        $currentEnrollment = $student->currentEnrollment();

        return Inertia::render('Admin/Enrollments/StudentHistory', [
            'student' => [
                'id' => $student->id,
                'name' => $student->user?->name,
                'roll_number' => $student->roll_number,
                'batch_name' => $student->batch?->name,
            ],
            'enrollments' => $enrollments->map(fn (Enrollment $enrollment) => [
                'id' => $enrollment->id,
                'class_display_name' => $enrollment->classModel?->displayName(),
                'session_name' => $enrollment->academicSession?->name,
                'enrolled_at' => $enrollment->enrolled_at?->toDateString(),
                'status' => $enrollment->status,
                'is_current' => $currentEnrollment !== null
                    && $currentEnrollment->id === $enrollment->id,
            ])->all(),
        ]);
    }

    /**
     * Refuse to act on a profile whose account is not a student.
     *
     * 404 for the same reason the students module does it: the account is
     * not part of this module at all, and saying "403" would confirm that
     * an id of that shape exists.
     */
    private function ensureStudent(StudentProfile $student): void
    {
        abort_unless($student->user?->isStudent(), 404);
    }
}
