<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\ClassModel;
use App\Models\ClassSubject;
use App\Models\Period;
use App\Models\Stream;
use App\Models\Subject;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeacherStudentTimetableTest extends TestCase
{
    use RefreshDatabase;

    private function academicSession(): AcademicSession
    {
        return AcademicSession::create([
            'name' => '2026-2027',
            'start_date' => '2026-08-01',
            'end_date' => '2027-05-31',
            'is_active' => true,
        ]);
    }

    private function stream(): Stream
    {
        return Stream::firstOrCreate(
            ['code' => 'PM'],
            ['name' => 'Pre-Medical', 'is_active' => true]
        );
    }

    private function classModel(
        AcademicSession $session,
        string $section = 'A'
    ): ClassModel {
        return ClassModel::create([
            'academic_session_id' => $session->id,
            'stream_id' => $this->stream()->id,
            'grade_level' => 11,
            'section' => $section,
            'capacity' => 50,
            'room' => 'R-101',
            'is_active' => true,
        ]);
    }

    private function period(
        AcademicSession $session,
        int $number = 1,
        bool $isBreak = false
    ): Period {
        // A period is unique per session and grid row, so a row that already
        // exists is reused rather than inserted again.
        $existing = Period::query()
            ->where('academic_session_id', $session->id)
            ->where('number', $number)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $start = 8 * 60 + ($number - 1) * 45;

        return Period::create([
            'academic_session_id' => $session->id,
            'number' => $number,
            'label' => $isBreak ? 'Break' : 'Period '.$number,
            'start_time' => sprintf('%02d:%02d', intdiv($start, 60), $start % 60),
            'end_time' => sprintf(
                '%02d:%02d',
                intdiv($start + 40, 60),
                ($start + 40) % 60
            ),
            'is_break' => $isBreak,
        ]);
    }

    private function teacher(): User
    {
        return User::factory()->create([
            'role' => 'teacher',
            'is_active' => true,
            'name' => 'Ahmed Khan',
        ]);
    }

    private function student(): User
    {
        return User::factory()->create([
            'role' => 'student',
            'is_active' => true,
            'name' => 'Test Student',
        ]);
    }

    /**
     * Give a teacher one scheduled cell in a class of their own.
     *
     * Each call gets a fresh section, because a class is unique per session,
     * grade, stream and section.
     */
    private function schedule(
        User $teacher,
        AcademicSession $session,
        int $day = 1,
        int $periodNumber = 1,
        string $subjectName = 'Physics',
        string $section = 'A'
    ): ClassSubject {
        $class = $this->classModel($session, $section);
        $period = $this->period($session, $periodNumber);

        $assignment = ClassSubject::create([
            'class_id' => $class->id,
            'subject_id' => Subject::create([
                'name' => $subjectName,
                'code' => 'PHY',
                'grade_level' => 11,
                'stream_id' => null,
                'is_active' => true,
            ])->id,
            'teacher_id' => $teacher->id,
            'periods_per_week' => 1,
        ]);

        TimetableSlot::create([
            'class_id' => $class->id,
            'period_id' => $period->id,
            'day_of_week' => $day,
            'class_subject_id' => $assignment->id,
        ]);

        return $assignment;
    }

    public function test_teacher_sees_their_own_slots_and_summary(): void
    {
        $session = $this->academicSession();
        $teacher = $this->teacher();
        $this->period($session, 2, true);
        $this->schedule($teacher, $session, 1, 1);

        $this->actingAs($teacher)
            ->get('/teacher/timetable')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Teacher/Timetable')
                ->where('teacher.name', 'Ahmed Khan')
                ->where('days.1', 'Monday')
                ->where('days.6', 'Saturday')
                ->has('periods', 2)
                // The break is flagged so the grid can merge the row.
                ->where('periods.1.is_break', true)
                ->has('slots', 1)
                ->where('slots.0.subject_name', 'Physics')
                ->where('slots.0.class_display_name', '11th Pre-Medical A')
                ->where('slots.0.day_of_week', 1)
                ->where('summary.total_periods', 1)
                ->where('summary.classes_count', 1)
                ->where('daySummary.1', 1)
                ->where('daySummary.2', 0));
    }

    public function test_teacher_only_sees_their_own_assignments(): void
    {
        $session = $this->academicSession();
        $mine = $this->teacher();
        $other = $this->teacher();

        $this->schedule($mine, $session, 1, 1, 'Physics', 'A');
        $this->schedule($other, $session, 2, 1, 'Biology', 'B');

        $this->actingAs($mine)
            ->get('/teacher/timetable')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('slots', 1)
                ->where('slots.0.subject_name', 'Physics')
                ->where('summary.total_periods', 1));
    }

    public function test_teacher_with_no_schedule_still_gets_a_page(): void
    {
        $session = $this->academicSession();
        $this->period($session, 1);
        $teacher = $this->teacher();

        // No assignments: the active session supplies the period rows.
        $this->actingAs($teacher)
            ->get('/teacher/timetable')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Teacher/Timetable')
                ->has('periods', 1)
                ->has('slots', 0)
                ->where('summary.total_periods', 0)
                ->where('summary.classes_count', 0));
    }

    public function test_student_sees_the_no_class_state(): void
    {
        $student = $this->student();

        $this->actingAs($student)
            ->get('/student/timetable')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Timetable')
                ->where('student.name', 'Test Student')
                ->where('noClass', true)
                ->has('periods', 0)
                ->has('slots', 0)
                ->where('days.1', 'Monday')
                ->where('days.6', 'Saturday'));
    }

    public function test_a_teacher_cannot_open_the_student_timetable(): void
    {
        $this->actingAs($this->teacher())
            ->get('/student/timetable')
            ->assertForbidden();
    }

    public function test_a_student_cannot_open_the_teacher_timetable(): void
    {
        $this->actingAs($this->student())
            ->get('/teacher/timetable')
            ->assertForbidden();
    }

    public function test_an_admin_cannot_open_either_timetable(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/teacher/timetable')->assertForbidden();
        $this->actingAs($admin)->get('/student/timetable')->assertForbidden();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/teacher/timetable')->assertRedirect('/login');
        $this->get('/student/timetable')->assertRedirect('/login');
    }
}
