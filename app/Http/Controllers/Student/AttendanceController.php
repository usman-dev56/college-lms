<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The student's own attendance record.
 *
 * Everything is read from the signed-in account; no id is taken from the
 * request, so one student cannot reach another's record by editing a query
 * string.
 */
class AttendanceController extends Controller
{
    /**
     * Show the attendance summary.
     *
     * The whole summary - overall and per subject - comes back because the
     * percentage and the breakdown behind it have to agree, and computing one
     * here and one there would let them drift.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // The route already requires the student role; this keeps the page
        // safe if the controller is ever reached without it.
        abort_unless($user?->isStudent(), 403);

        $student = $user->studentProfile;

        // No profile yet is a normal state, not an error: the account exists
        // but the office has not admitted them, so there is nothing to report
        // on and the page says so instead of showing zeroes.
        if ($student === null) {
            return Inertia::render('Student/Attendance/Index', [
                'student' => null,
                'summary' => null,
                'message' => 'Your student profile has not been created yet. Please contact the administration.',
            ]);
        }

        $student->load(['batch']);

        return Inertia::render('Student/Attendance/Index', [
            'student' => [
                'name' => $user->name,
                'roll_number' => $student->roll_number,
                'batch_name' => $student->batch?->name,
            ],
            'summary' => app(AttendanceService::class)->summaryForStudent($student->id),
            'message' => null,
        ]);
    }
}
