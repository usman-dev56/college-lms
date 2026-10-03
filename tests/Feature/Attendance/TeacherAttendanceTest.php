<?php

namespace Tests\Feature\Attendance;

use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\ClassModel;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\Period;
use App\Models\StudentBatch;
use App\Models\StudentProfile;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The teacher's marking page: the day's periods, who may open a register, and
 * what a submission is allowed to write.
 *
 * The ownership rule is the important one. A class-subject id is a guessable
 * integer, so "can this teacher mark period 3 of Class 11-A" has to be answered
 * from the database rather than from anything the browser sent.
 */
class TeacherAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $today;

    protected function setUp(): void
    {
        parent::setUp();

        // Frozen so a test that runs either side of midnight, or over a
        // weekend boundary, cannot see a different day than the one it built
        // a timetable for. Monday, which is a normal teaching day.
        $this->today = Carbon::parse('2026-09-21')->startOfDay();
        Carbon::setTestNow($this->today);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * A session, stream, subject, class, period and a teaching assignment
     * owned by $teacher, with a timetable slot on the given weekday.
     *
     * @return array{session: AcademicSession, class: ClassModel, period: Period, classSubject: ClassSubject, teacher: User}
     */
    private function teaching(?User $teacher = null, ?int $dayOfWeek = null): array
    {
        $session = $this->createActiveSession();
        $stream = $this->createStream();
        $subject = $this->createSubject();
        $class = $this->createClass($session, $stream);
        $period = $this->createPeriod($session);
        $teacher ??= $this->createTeacher();

        $classSubject = ClassSubject::create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'periods_per_week' => 3,
        ]);

        TimetableSlot::create([
            'class_id' => $class->id,
            'period_id' => $period->id,
            'day_of_week' => $dayOfWeek ?? $this->today->dayOfWeekIso,
            'class_subject_id' => $classSubject->id,
            'room' => null,
        ]);

        return compact('session', 'class', 'period', 'classSubject', 'teacher');
    }

    /**
     * A break period in the same session - a slot the grid has but attendance
     * is never taken for.
     */
    private function breakPeriod(AcademicSession $session): Period
    {
        return $this->createPeriod($session, [
            'number' => 2,
            'label' => 'Lunch',
            'start_time' => '13:00',
            'end_time' => '13:45',
            'is_break' => true,
        ]);
    }

    private function enrolStudent(ClassModel $class, AcademicSession $session): StudentProfile
    {
        $batch = StudentBatch::firstOrCreate(
            ['name' => '2026-2028'],
            [
                'start_grade' => 11,
                'expected_graduation_year' => 2028,
                'is_active' => true,
            ],
        );

        $student = StudentProfile::create([
            'user_id' => $this->createStudent()->id,
            'batch_id' => $batch->id,
            'roll_number' => StudentProfile::nextRollNumber($batch->id),
            'status' => 'active',
        ]);

        Enrollment::create([
            'student_profile_id' => $student->id,
            'class_id' => $class->id,
            'academic_session_id' => $session->id,
            'enrolled_at' => now()->toDateString(),
            'status' => 'active',
        ]);

        return $student;
    }

    /**
     * The marking page's query string.
     *
     * @return array<string, mixed>
     */
    private function markQuery(array $s, ?string $date = null): array
    {
        return [
            'class_subject_id' => $s['classSubject']->id,
            'period_id' => $s['period']->id,
            'attendance_date' => $date ?? $this->today->toDateString(),
        ];
    }

    public function test_teacher_index_lists_todays_periods(): void
    {
        $s = $this->teaching();

        $this->actingAs($s['teacher'])
            ->get(route('teacher.attendance.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Teacher/Attendance/Index')
                ->where('attendance_date', $this->today->toDateString())
                ->where('is_sunday', false)
                ->where('teacher_name', $s['teacher']->name)
                ->has('slots', 1)
                ->where('slots.0.class_subject_id', $s['classSubject']->id)
                ->where('slots.0.period.id', $s['period']->id)
                ->where('slots.0.class.display_name', $s['class']->displayName())
                ->where('slots.0.subject.name', 'English')
            );
    }

    public function test_teacher_index_shows_empty_state_on_sunday(): void
    {
        // A slot on a Monday, viewed on a Sunday.
        $s = $this->teaching(null, 1);

        Carbon::setTestNow(Carbon::parse('2026-09-20')->startOfDay());

        $this->actingAs($s['teacher'])
            ->get(route('teacher.attendance.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                // Sunday is passed to the page so it can say what happened,
                // rather than rendering as "you have no periods scheduled",
                // which reads as a mistake.
                ->where('is_sunday', true)
                ->where('today_name', 'Sunday')
                ->where('attendance_date', '2026-09-20')
                ->has('slots', 0)
            );
    }

    public function test_teacher_can_open_marking_page_for_own_class_subject(): void
    {
        $s = $this->teaching();
        $student = $this->enrolStudent($s['class'], $s['session']);

        $this->actingAs($s['teacher'])
            ->get(route('teacher.attendance.mark', $this->markQuery($s)))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Teacher/Attendance/Mark')
                ->where('class_subject.id', $s['classSubject']->id)
                ->where('class_subject.subject_name', 'English')
                ->where('class.display_name', $s['class']->displayName())
                ->where('period.id', $s['period']->id)
                ->where('attendance_date', $this->today->toDateString())
                // The current roll, so the page never offers a stale student.
                ->has('students', 1)
                ->where('students.0.student_profile_id', $student->id)
                ->where('students.0.status', null)
                ->where('is_locked', false)
            );
    }

    public function test_teacher_cannot_open_marking_page_for_another_teachers_class_subject(): void
    {
        $owner = $this->teaching();
        $intruder = $this->createTeacher();

        $this->actingAs($intruder)
            ->get(route('teacher.attendance.mark', $this->markQuery($owner)))
            ->assertForbidden();
    }

    public function test_teacher_can_submit_attendance_for_all_students(): void
    {
        $s = $this->teaching();
        $first = $this->enrolStudent($s['class'], $s['session']);
        $second = $this->enrolStudent($s['class'], $s['session']);

        $response = $this->actingAs($s['teacher'])
            ->post(route('teacher.attendance.store'), [
                ...$this->markQuery($s),
                'records' => [
                    ['student_profile_id' => $first->id, 'status' => Attendance::STATUS_PRESENT],
                    ['student_profile_id' => $second->id, 'status' => Attendance::STATUS_ABSENT],
                ],
            ]);

        $response->assertRedirect();

        $this->assertSame(2, Attendance::count());
        $this->assertDatabaseHas('attendances', [
            'student_profile_id' => $first->id,
            'status' => Attendance::STATUS_PRESENT,
        ]);
        $this->assertDatabaseHas('attendances', [
            'student_profile_id' => $second->id,
            'status' => Attendance::STATUS_ABSENT,
        ]);
    }

    public function test_teacher_cannot_submit_attendance_for_a_break_period(): void
    {
        $s = $this->teaching();
        $break = $this->breakPeriod($s['session']);
        $student = $this->enrolStudent($s['class'], $s['session']);

        $this->actingAs($s['teacher'])
            ->post(route('teacher.attendance.store'), [
                'class_subject_id' => $s['classSubject']->id,
                'period_id' => $break->id,
                'attendance_date' => $this->today->toDateString(),
                'records' => [
                    ['student_profile_id' => $student->id, 'status' => Attendance::STATUS_PRESENT],
                ],
            ])
            // A break is refused outright, before any record is written, so attendance
        // is never taken for a period the college does not teach in.
            ->assertStatus(422);

        $this->assertSame(0, Attendance::count());
    }

    public function test_teacher_cannot_submit_attendance_twice_for_the_same_period(): void
    {
        $s = $this->teaching();
        $student = $this->enrolStudent($s['class'], $s['session']);

        $payload = [
            ...$this->markQuery($s),
            'records' => [
                ['student_profile_id' => $student->id, 'status' => Attendance::STATUS_PRESENT],
            ],
        ];

        $this->actingAs($s['teacher'])
            ->post(route('teacher.attendance.store'), $payload)
            ->assertRedirect();

        // A second submission for the same period is refused, so a register
        // cannot be marked twice and quietly lose the first set of marks.
        $this->actingAs($s['teacher'])
            ->post(route('teacher.attendance.store'), [
                ...$payload,
                'records' => [
                    ['student_profile_id' => $student->id, 'status' => Attendance::STATUS_ABSENT],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, Attendance::count());
        $this->assertSame(
            Attendance::STATUS_PRESENT,
            Attendance::first()->status,
        );
    }

    public function test_teacher_attendance_creates_a_record_per_student(): void
    {
        $s = $this->teaching();
        $first = $this->enrolStudent($s['class'], $s['session']);
        $second = $this->enrolStudent($s['class'], $s['session']);

        $this->actingAs($s['teacher'])
            ->post(route('teacher.attendance.store'), [
                ...$this->markQuery($s),
                'records' => [
                    ['student_profile_id' => $first->id, 'status' => Attendance::STATUS_PRESENT],
                    ['student_profile_id' => $second->id, 'status' => Attendance::STATUS_PRESENT],
                ],
            ])
            ->assertRedirect();

        // One row per student, all sharing the period, date and marker.
        $this->assertSame(2, Attendance::count());

        foreach (Attendance::all() as $attendance) {
            $this->assertSame($s['classSubject']->id, $attendance->class_subject_id);
            $this->assertSame($s['period']->id, $attendance->period_id);
            $this->assertSame($s['teacher']->id, $attendance->marked_by);
            $this->assertSame($this->today->toDateString(), $attendance->attendance_date->toDateString());
        }
    }

    public function test_teacher_cannot_submit_attendance_for_a_student_not_enrolled(): void
    {
        $s = $this->teaching();
        $enrolled = $this->enrolStudent($s['class'], $s['session']);

        // A real profile, in a real batch, but never enrolled in this class.
        $batch = StudentBatch::firstOrCreate(
            ['name' => '2026-2028'],
            [
                'start_grade' => 11,
                'expected_graduation_year' => 2028,
                'is_active' => true,
            ],
        );

        $stranger = StudentProfile::create([
            'user_id' => $this->createStudent()->id,
            'batch_id' => $batch->id,
            'roll_number' => StudentProfile::nextRollNumber($batch->id).'X',
            'status' => 'active',
        ]);

        $this->actingAs($s['teacher'])
            ->post(route('teacher.attendance.store'), [
                ...$this->markQuery($s),
                'records' => [
                    ['student_profile_id' => $enrolled->id, 'status' => Attendance::STATUS_PRESENT],
                    ['student_profile_id' => $stranger->id, 'status' => Attendance::STATUS_PRESENT],
                ],
            ])
            ->assertRedirect();

        // The enrolled student is marked; the stranger is skipped rather than
        // written, and the rest of the register is not lost over one bad id.
        $this->assertSame(1, Attendance::count());
        $this->assertDatabaseHas('attendances', [
            'student_profile_id' => $enrolled->id,
        ]);
        $this->assertDatabaseMissing('attendances', [
            'student_profile_id' => $stranger->id,
        ]);
    }

    public function test_non_teacher_cannot_access_teacher_attendance(): void
    {
        $s = $this->teaching();
        $student = $this->enrolStudent($s['class'], $s['session']);

        // A student account cannot reach the teacher's pages.
        $this->actingAs($student->user)
            ->get(route('teacher.attendance.index'))
            ->assertForbidden();

        $this->actingAs($student->user)
            ->get(route('teacher.attendance.mark', $this->markQuery($s)))
            ->assertForbidden();

        // Nor can an admin.
        $this->actingAs($this->createAdmin())
            ->get(route('teacher.attendance.index'))
            ->assertForbidden();
    }
}
