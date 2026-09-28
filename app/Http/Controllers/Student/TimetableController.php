<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TimetableController extends Controller
{
    /**
     * The school week, 1 = Monday .. 6 = Saturday.
     *
     * @var array<int, string>
     */
    private const DAYS = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    /**
     * Show the student's own weekly timetable.
     *
     * Students are not enrolled in classes yet - that arrives with the
     * student-class enrollment work. Until then this renders the no-class
     * state, so the page and its navigation are in place before there is
     * anything to show.
     */
    public function index(Request $request): Response
    {
        $student = $request->user();

        // The route already requires the student role; this keeps the page
        // safe if the controller is reused from somewhere without it.
        abort_unless($student?->isStudent(), 403);

        /*
         * Level 3 hook: students will get an enrollment (a student_class or
         * enrolments table) tying them to a class. Once that exists, this
         * resolves the student's class, loads that class's periods and
         * slots, and sets noClass to false - the same shape the grid already
         * expects, with teacher_name filled in instead of null.
         */
        $enrollments = [];

        return Inertia::render('Student/Timetable', [
            'periods' => [],
            'days' => self::DAYS,
            'slots' => [],
            'student' => ['name' => $student->name],
            'noClass' => $enrollments === [],
        ]);
    }
}
