<?php

namespace Tests\Feature\Attendance;

use App\Models\ClassModel;
use App\Models\ClassSubject;
use App\Models\Period;
use App\Models\StudentBatch;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who may see which part of the attendance feature.
 *
 * The three areas are deliberately separate: the office sees the whole
 * register, a teacher sees the periods they teach, and a student sees their
 * own record and nothing else. Every test here is one of the six possible
 * role-to-area combinations being refused, or - for the student - being
 * granted against their own data and denied against someone else's.
 */
class AttendancePermissionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * An admin, a teacher and two students.
     *
     * @return array{admin: User, teacher: User, class: ClassModel, period: Period, first: StudentProfile, second: StudentProfile}
     */
    private function people(): array
    {
        $admin = $this->createAdmin();
        $teacher = $this->createTeacher();

        $session = $this->createActiveSession();
        $class = $this->createClass($session, $this->createStream());
        $period = $this->createPeriod($session);

        ClassSubject::create([
            'class_id' => $class->id,
            'subject_id' => $this->createSubject()->id,
            'teacher_id' => $teacher->id,
            'periods_per_week' => 3,
        ]);

        $batch = StudentBatch::firstOrCreate(
            ['name' => '2026-2028'],
            [
                'start_grade' => 11,
                'expected_graduation_year' => 2028,
                'is_active' => true,
            ],
        );

        $students = [];

        foreach (['001', '002'] as $roll) {
            $students[] = StudentProfile::create([
                'user_id' => $this->createStudent()->id,
                'batch_id' => $batch->id,
                'roll_number' => StudentProfile::nextRollNumber($batch->id),
                'status' => 'active',
            ]);
        }

        return [
            'admin' => $admin,
            'teacher' => $teacher,
            'class' => $class,
            'period' => $period,
            'first' => $students[0],
            'second' => $students[1],
        ];
    }

    public function test_guest_is_redirected_from_admin_attendance(): void
    {
        $this->get(route('admin.attendance.index'))->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_from_teacher_attendance(): void
    {
        $this->get(route('teacher.attendance.index'))->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_from_student_attendance(): void
    {
        $this->get(route('student.attendance'))->assertRedirect(route('login'));
    }

    public function test_student_cannot_access_admin_attendance(): void
    {
        $p = $this->people();

        $this->actingAs($p['first']->user)
            ->get(route('admin.attendance.index'))
            ->assertForbidden();
    }

    public function test_student_cannot_access_teacher_attendance(): void
    {
        $p = $this->people();

        $this->actingAs($p['first']->user)
            ->get(route('teacher.attendance.index'))
            ->assertForbidden();
    }

    public function test_teacher_cannot_access_admin_attendance(): void
    {
        $p = $this->people();

        $this->actingAs($p['teacher'])
            ->get(route('admin.attendance.index'))
            ->assertForbidden();
    }

    public function test_teacher_cannot_access_student_attendance(): void
    {
        $p = $this->people();

        $this->actingAs($p['teacher'])
            ->get(route('student.attendance'))
            ->assertForbidden();
    }

    public function test_admin_cannot_access_teacher_attendance(): void
    {
        $p = $this->people();

        $this->actingAs($p['admin'])
            ->get(route('teacher.attendance.index'))
            ->assertForbidden();
    }

    public function test_admin_cannot_access_student_attendance(): void
    {
        $p = $this->people();

        $this->actingAs($p['admin'])
            ->get(route('student.attendance'))
            ->assertForbidden();
    }

    public function test_student_can_view_own_attendance(): void
    {
        $p = $this->people();

        $this->actingAs($p['first']->user)
            ->get(route('student.attendance'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Student/Attendance/Index')
                ->where('student.roll_number', $p['first']->roll_number)
                ->where('message', null)
                // A student with no marks still has a page: the summary is
                // present with a null percentage, not absent.
                ->has('summary')
            );
    }

    public function test_student_cannot_view_another_students_attendance(): void
    {
        $p = $this->people();

        // The page takes no id at all - the summary is resolved from the
        // signed-in account - so there is nothing to tamper with. Signing in
        // as the second student proves the view follows the session rather
        // than anything the request carried.
        $this->actingAs($p['second']->user)
            ->get(route('student.attendance'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('student.roll_number', $p['second']->roll_number)
            );

        // A guess at the other student's record is not a route that exists.
        $this->actingAs($p['first']->user)
            ->get('/student/attendance?student_profile_id='.$p['second']->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('student.roll_number', $p['first']->roll_number)
            );
    }
}
