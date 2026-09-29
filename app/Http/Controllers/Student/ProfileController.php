<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * The student's own record, read-only.
     *
     * There is no edit form on purpose. Almost everything here is what the
     * college office holds on a student's record, and letting a student
     * rewrite their own CNIC or their matric marks would be a way to
     * disqualify themselves and to create duplicates at the same time. A
     * change goes through the office, which is what the note at the bottom
     * of the page says.
     *
     * Everything is read from the signed-in account; no id is taken from
     * the request, so one student cannot reach another's record.
     */
    public function show(Request $request): Response
    {
        $user = $request->user();

        // The route already requires the student role; this keeps the page
        // safe if the controller is ever reached without it.
        abort_unless($user?->isStudent(), 403);

        $student = $user->studentProfile;

        if (! $student) {
            return Inertia::render('Student/Profile', [
                'student' => null,
                'message' => 'Your student profile has not been created yet.',
            ]);
        }

        $student->load(['batch']);

        return Inertia::render('Student/Profile', [
            'student' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_active' => (bool) $user->is_active,
                'roll_number' => $student->roll_number,
                'batch_name' => $student->batch?->name,
                'cnic_bform' => $student->cnic_bform,
                'father_name' => $student->father_name,
                'date_of_birth' => $student->date_of_birth?->toDateString(),
                'gender' => $student->gender,
                'guardian_phone' => $student->guardian_phone,
                'address' => $student->address,
                'admission_date' => $student->admission_date?->toDateString(),
                'previous_school' => $student->previous_school,
                'previous_marks_obtained' => $student->previous_marks_obtained,
                'previous_marks_total' => $student->previous_marks_total,
                'status' => $student->status,
            ],
            'message' => null,
        ]);
    }
}
