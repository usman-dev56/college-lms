<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\ClassModel;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\Stream;
use App\Models\StudentBatch;
use App\Models\StudentProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Enrollments: the class roster, enrolling and un-enrolling, a student's own
 * view of the class they are in, and the auto-enrollment that happens when an
 * accepted admission is converted.
 *
 * The helpers below build the smallest world each test needs. The session,
 * stream, class, period and subject come from TestCase; a batch helper does not
 * exist there and every student here needs one, so it is made here. enroll()
 * writes the row directly rather than posting the form, so a test that only
 * needs a roster on screen does not have to arrange one.
 */
class EnrollmentsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * firstOrCreate rather than create: the name has a unique index, and a
     * test that asks for the same cohort twice must not trip it.
     */
    private function batch(string $name = '2026-2028'): StudentBatch
    {
        return StudentBatch::firstOrCreate(
            ['name' => $name],
            [
                'start_grade' => 11,
                'expected_graduation_year' => (int) substr($name, -4),
                'is_active' => true,
            ],
        );
    }

    /**
     * A student account with a profile, so the roster, the history and the
     * student portal all have something real to bind to.
     *
     * @param  array<string, mixed>  $userAttributes
     * @param  array<string, mixed>  $profileAttributes
     */
    private function student(
        array $userAttributes = [],
        array $profileAttributes = [],
    ): StudentProfile {
        $batch = $this->batch();

        return StudentProfile::create([
            'user_id' => $this->createStudent($userAttributes)->id,
            'batch_id' => $batch->id,
            'roll_number' => StudentProfile::nextRollNumber($batch->id),
            'status' => 'active',
            ...$profileAttributes,
        ]);
    }

    /**
     * A live enrollment row.
     *
     * The session is taken from the class rather than passed in, because that
     * is the only value the application ever writes - and the partial unique
     * index on (student, session) depends on the two always agreeing.
     */
    private function enroll(StudentProfile $student, ClassModel $class): Enrollment
    {
        return Enrollment::create([
            'student_profile_id' => $student->id,
            'class_id' => $class->id,
            'academic_session_id' => $class->academic_session_id,
            'enrolled_at' => now()->toDateString(),
            'status' => 'active',
        ]);
    }

    /**
     * An accepted application waiting to be converted.
     *
     * Each call gets its own application number and CNIC, both of which are
     * unique across the table.
     */
    private function acceptedAdmission(Stream $stream, ?StudentBatch $batch = null): Admission
    {
        static $counter = 0;
        $counter++;

        return Admission::create([
            'application_number' => 'ADM-2026-'.str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
            'applicant_name' => 'Hamza Yousaf',
            'father_name' => 'Muhammad Yousaf',
            'cnic_bform' => sprintf('35202-%07d-1', $counter),
            'phone' => sprintf('0300-%07d', $counter),
            'stream_applied_id' => $stream->id,
            'batch_id' => ($batch ?? $this->batch())->id,
            'status' => 'accepted',
        ]);
    }

    public function test_class_roster_page_renders_for_admin(): void
    {
        $session = $this->createActiveSession();
        $class = $this->createClass($session, $this->createStream());

        $enrolled = $this->student(['name' => 'Ahmed Khan']);
        $waiting = $this->student(['name' => 'Bilal Ahmad']);

        $this->enroll($enrolled, $class);

        $this->actingAs($this->createAdmin())
            ->get("/admin/classes/{$class->id}/enrollments")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Enrollments/ClassRoster')
                ->where('class.display_name', $class->displayName())
                ->where('class.grade_level', 11)
                ->where('class.stream_name', 'Pre-Medical')
                ->where('class.session_name', '2026-2027')
                ->has('students', 1)
                ->where('students.0.name', 'Ahmed Khan')
                ->where('students.0.status', 'active')
                // The student who is not on the roll is offered for selection.
                ->has('unassignedStudents', 1)
                ->where('unassignedStudents.0.id', $waiting->id));
    }

    public function test_non_admin_cannot_view_class_roster(): void
    {
        $session = $this->createActiveSession();
        $class = $this->createClass($session, $this->createStream());

        $this->actingAs($this->createTeacher())
            ->get("/admin/classes/{$class->id}/enrollments")
            ->assertForbidden();
    }

    public function test_admin_can_bulk_enroll_students_into_a_class(): void
    {
        $session = $this->createActiveSession();
        $class = $this->createClass($session, $this->createStream());

        $first = $this->student();
        $second = $this->student();

        $this->actingAs($this->createAdmin())
            ->from("/admin/classes/{$class->id}/enrollments")
            ->post("/admin/classes/{$class->id}/enrollments", [
                'class_id' => $class->id,
                'student_profile_ids' => [$first->id, $second->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect("/admin/classes/{$class->id}/enrollments")
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'Enrolled 2'));

        $this->assertDatabaseCount('enrollments', 2);

        foreach ([$first, $second] as $student) {
            $this->assertDatabaseHas('enrollments', [
                'student_profile_id' => $student->id,
                'class_id' => $class->id,
                'academic_session_id' => $session->id,
                'status' => 'active',
            ]);
        }
    }

    public function test_bulk_enrollment_skips_students_already_enrolled_in_the_same_session(): void
    {
        $session = $this->createActiveSession();
        $class = $this->createClass($session, $this->createStream());

        $student = $this->student();
        $other = $this->student();

        // The same profile id twice in one payload: the first pass enrolls
        // them, the second finds the row it just wrote and skips it.
        $this->actingAs($this->createAdmin())
            ->from("/admin/classes/{$class->id}/enrollments")
            ->post("/admin/classes/{$class->id}/enrollments", [
                'class_id' => $class->id,
                'student_profile_ids' => [$student->id, $student->id, $other->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect()
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'skipped'));

        // One row per student, not one per submitted id.
        $this->assertDatabaseCount('enrollments', 2);
        $this->assertSame(
            1,
            Enrollment::where('student_profile_id', $student->id)->count(),
        );
    }

    public function test_a_student_cannot_be_enrolled_in_two_classes_in_the_same_session(): void
    {
        $session = $this->createActiveSession();
        $stream = $this->createStream();

        $first = $this->createClass($session, $stream, ['section' => 'A']);
        $second = $this->createClass($session, $stream, ['section' => 'B']);

        $student = $this->student();
        $this->enroll($student, $first);

        // A different class, the same session: the office can try, but the
        // student is skipped rather than given a second live row.
        $this->actingAs($this->createAdmin())
            ->from("/admin/classes/{$second->id}/enrollments")
            ->post("/admin/classes/{$second->id}/enrollments", [
                'class_id' => $second->id,
                'student_profile_ids' => [$student->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('enrollments', 1);
        $this->assertDatabaseMissing('enrollments', [
            'student_profile_id' => $student->id,
            'class_id' => $second->id,
        ]);
    }

    public function test_admin_can_unenroll_a_student(): void
    {
        $session = $this->createActiveSession();
        $class = $this->createClass($session, $this->createStream());

        $student = $this->student();
        $enrollment = $this->enroll($student, $class);

        $this->actingAs($this->createAdmin())
            ->from("/admin/classes/{$class->id}/enrollments")
            ->delete("/admin/enrollments/{$enrollment->id}")
            ->assertSessionHasNoErrors()
            ->assertRedirect("/admin/classes/{$class->id}/enrollments")
            ->assertSessionHas('success');

        // The row is soft deleted, and the surviving row says why it ended
        // rather than reading 'active' on something nothing will read again.
        $this->assertSoftDeleted('enrollments', ['id' => $enrollment->id]);
        $this->assertSame('withdrawn', $enrollment->fresh()->status);
    }

    public function test_unenrolled_student_appears_in_the_unassigned_list_again(): void
    {
        $session = $this->createActiveSession();
        $class = $this->createClass($session, $this->createStream());

        $student = $this->student(['name' => 'Ahmed Khan']);
        $enrollment = $this->enroll($student, $class);

        $this->actingAs($this->createAdmin())
            ->get("/admin/classes/{$class->id}/enrollments")
            ->assertInertia(fn (Assert $page) => $page
                ->has('students', 1)
                ->has('unassignedStudents', 0));

        $this->actingAs($this->createAdmin())
            ->delete("/admin/enrollments/{$enrollment->id}")
            ->assertSessionHasNoErrors();

        // Back on the candidates list, because the session-scoped "already
        // enrolled" check no longer sees a live row for them.
        $this->actingAs($this->createAdmin())
            ->get("/admin/classes/{$class->id}/enrollments")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('students', 0)
                ->has('unassignedStudents', 1)
                ->where('unassignedStudents.0.id', $student->id));
    }

    public function test_bulk_unenroll_removes_multiple_students_at_once(): void
    {
        $session = $this->createActiveSession();
        $class = $this->createClass($session, $this->createStream());

        $first = $this->student();
        $second = $this->student();

        $firstEnrollment = $this->enroll($first, $class);
        $secondEnrollment = $this->enroll($second, $class);

        $this->actingAs($this->createAdmin())
            ->from("/admin/classes/{$class->id}/enrollments")
            ->delete('/admin/enrollments', [
                'enrollment_ids' => [$firstEnrollment->id, $secondEnrollment->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect("/admin/classes/{$class->id}/enrollments")
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'Removed 2'));

        $this->assertSoftDeleted('enrollments', ['id' => $firstEnrollment->id]);
        $this->assertSoftDeleted('enrollments', ['id' => $secondEnrollment->id]);
        $this->assertSame(0, $class->activeEnrollmentCount());
    }

    public function test_student_enrollment_history_page_renders_for_admin(): void
    {
        $session = $this->createActiveSession();
        $class = $this->createClass($session, $this->createStream());

        $student = $this->student(['name' => 'Ahmed Khan']);
        $enrollment = $this->enroll($student, $class);

        $this->actingAs($this->createAdmin())
            ->get("/admin/students/{$student->id}/enrollments")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Enrollments/StudentHistory')
                ->where('student.id', $student->id)
                ->where('student.name', 'Ahmed Khan')
                ->where('student.batch_name', '2026-2028')
                ->has('enrollments', 1)
                ->where('enrollments.0.id', $enrollment->id)
                ->where('enrollments.0.class_display_name', $class->displayName())
                ->where('enrollments.0.session_name', '2026-2027')
                ->where('enrollments.0.status', 'active')
                ->where('enrollments.0.is_current', true));
    }

    public function test_non_admin_cannot_view_student_enrollment_history(): void
    {
        $session = $this->createActiveSession();
        $class = $this->createClass($session, $this->createStream());

        $student = $this->student();
        $this->enroll($student, $class);

        $this->actingAs($this->createTeacher())
            ->get("/admin/students/{$student->id}/enrollments")
            ->assertForbidden();
    }

    public function test_student_portal_dashboard_shows_class_when_enrolled(): void
    {
        $session = $this->createActiveSession();
        $stream = $this->createStream();
        $class = $this->createClass($session, $stream);

        $student = $this->student(['name' => 'Ahmed Khan']);
        $this->enroll($student, $class);

        $this->actingAs($student->user)
            ->get('/student/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Dashboard')
                ->where('student.name', 'Ahmed Khan')
                ->where('classInfo.display_name', $class->displayName())
                ->where('classInfo.grade_level', 11)
                ->where('classInfo.section', 'A')
                ->where('classInfo.stream_name', 'Pre-Medical'));
    }

    public function test_student_portal_dashboard_shows_no_class_when_not_enrolled(): void
    {
        $this->createActiveSession();

        $student = $this->student(['name' => 'Ahmed Khan']);

        $this->actingAs($student->user)
            ->get('/student/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Dashboard')
                ->where('student.name', 'Ahmed Khan')
                ->where('classInfo', null)
                ->has('todaySchedule', 0));
    }

    public function test_student_portal_profile_page_renders(): void
    {
        $this->createActiveSession();

        $student = $this->student(
            ['name' => 'Ahmed Khan'],
            ['father_name' => 'Muhammad Khan'],
        );

        $this->actingAs($student->user)
            ->get('/student/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Profile')
                ->where('student.name', 'Ahmed Khan')
                ->where('student.email', $student->user->email)
                ->where('student.roll_number', $student->roll_number)
                ->where('student.batch_name', '2026-2028')
                ->where('student.father_name', 'Muhammad Khan')
                ->where('student.status', 'active')
                ->where('message', null));
    }

    public function test_student_portal_timetable_shows_class_schedule_when_enrolled(): void
    {
        $session = $this->createActiveSession();
        $stream = $this->createStream();
        $class = $this->createClass($session, $stream);

        // A saved timetable, written through the real admin route so the class
        // has a grid the student page can render.
        $period = $this->createPeriod($session);
        $assignment = ClassSubject::create([
            'class_id' => $class->id,
            'subject_id' => $this->createSubject(['name' => 'Physics', 'code' => 'PHY'])->id,
            'teacher_id' => $this->createTeacher(['name' => 'Bilal Ahmad'])->id,
            'periods_per_week' => 1,
        ]);

        $this->actingAs($this->createAdmin())
            ->put("/admin/classes/{$class->id}/timetable", [
                'slots' => [[
                    'day_of_week' => 1,
                    'period_id' => $period->id,
                    'class_subject_id' => $assignment->id,
                ]],
            ])
            ->assertSessionHasNoErrors();

        $student = $this->student();
        $this->enroll($student, $class);

        $this->actingAs($student->user)
            ->get('/student/timetable')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Timetable')
                ->where('noClass', false)
                ->where('classInfo.display_name', $class->displayName())
                ->has('slots', 1)
                ->where('slots.0.day_of_week', 1)
                ->where('slots.0.subject_name', 'Physics')
                ->where('slots.0.teacher_name', 'Bilal Ahmad'));
    }

    public function test_student_portal_timetable_shows_empty_state_when_not_enrolled(): void
    {
        $this->createActiveSession();

        $student = $this->student();

        $this->actingAs($student->user)
            ->get('/student/timetable')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Timetable')
                ->where('noClass', true)
                ->where('classInfo', null)
                ->has('slots', 0)
                ->has('periods', 0));
    }

    public function test_student_cannot_access_admin_enrollment_routes(): void
    {
        $session = $this->createActiveSession();
        $class = $this->createClass($session, $this->createStream());

        $student = $this->student();
        $enrollment = $this->enroll($student, $class);

        $studentUser = $student->user;

        $this->actingAs($studentUser)
            ->get("/admin/classes/{$class->id}/enrollments")
            ->assertForbidden();

        $this->actingAs($studentUser)
            ->post("/admin/classes/{$class->id}/enrollments", [
                'class_id' => $class->id,
                'student_profile_ids' => [$student->id],
            ])
            ->assertForbidden();

        $this->actingAs($studentUser)
            ->delete("/admin/enrollments/{$enrollment->id}")
            ->assertForbidden();

        $this->actingAs($studentUser)
            ->delete('/admin/enrollments', ['enrollment_ids' => [$enrollment->id]])
            ->assertForbidden();

        $this->actingAs($studentUser)
            ->get("/admin/students/{$student->id}/enrollments")
            ->assertForbidden();

        // Nothing was written and nothing was removed.
        $this->assertDatabaseHas('enrollments', [
            'id' => $enrollment->id,
            'deleted_at' => null,
            'status' => 'active',
        ]);
    }

    public function test_teacher_cannot_access_admin_enrollment_routes(): void
    {
        $session = $this->createActiveSession();
        $class = $this->createClass($session, $this->createStream());

        $student = $this->student();
        $enrollment = $this->enroll($student, $class);

        $teacher = $this->createTeacher();

        $this->actingAs($teacher)
            ->get("/admin/classes/{$class->id}/enrollments")
            ->assertForbidden();

        $this->actingAs($teacher)
            ->post("/admin/classes/{$class->id}/enrollments", [
                'class_id' => $class->id,
                'student_profile_ids' => [$student->id],
            ])
            ->assertForbidden();

        $this->actingAs($teacher)
            ->delete("/admin/enrollments/{$enrollment->id}")
            ->assertForbidden();

        $this->actingAs($teacher)
            ->delete('/admin/enrollments', ['enrollment_ids' => [$enrollment->id]])
            ->assertForbidden();

        $this->actingAs($teacher)
            ->get("/admin/students/{$student->id}/enrollments")
            ->assertForbidden();

        $this->assertDatabaseHas('enrollments', [
            'id' => $enrollment->id,
            'deleted_at' => null,
            'status' => 'active',
        ]);
    }

    public function test_admission_convert_auto_enrolls_student_when_matching_class_exists(): void
    {
        $session = $this->createActiveSession();
        $stream = $this->createStream();
        $class = $this->createClass($session, $stream, ['grade_level' => 11]);

        $admission = $this->acceptedAdmission($stream);

        $this->actingAs($this->createAdmin())
            ->patch("/admin/admissions/{$admission->id}/convert")
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $admission->refresh();

        $this->assertSame('enrolled', $admission->status);

        $profile = StudentProfile::find($admission->enrolled_student_profile_id);

        $this->assertNotNull($profile);

        $enrollment = Enrollment::where('student_profile_id', $profile->id)
            ->where('status', 'active')
            ->first();

        $this->assertNotNull($enrollment);
        $this->assertSame($class->id, $enrollment->class_id);
        $this->assertSame($session->id, $enrollment->academic_session_id);
    }

    public function test_admission_convert_does_not_crash_when_no_matching_class_exists(): void
    {
        $this->createActiveSession();
        $stream = $this->createStream();

        // Deliberately no class: admissions are for Grade 11, and a stream
        // that is not running one this session is a normal situation.
        $admission = $this->acceptedAdmission($stream);

        $this->actingAs($this->createAdmin())
            ->patch("/admin/admissions/{$admission->id}/convert")
            ->assertSessionHasNoErrors();

        $admission->refresh();

        $this->assertSame('enrolled', $admission->status);

        $profile = StudentProfile::find($admission->enrolled_student_profile_id);

        $this->assertNotNull($profile);

        // The student is created and on the roll, just not in a class.
        $this->assertSame(0, $profile->enrollments()->count());
        $this->assertDatabaseCount('enrollments', 0);
    }
}
