<?php

namespace Tests\Feature\Attendance;

use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\ClassModel;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\Period;
use App\Models\Stream;
use App\Models\StudentBatch;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The percentage rules, which everything else in the attendance feature is
 * built on top of.
 *
 * These call the service directly rather than going through HTTP. A percentage
 * is a definition, not a page: if "late counts as present" ever changes, it
 * changes here first, and the reports that read the same figure need no
 * changes of their own.
 */
class AttendanceServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * How many marks this test has created, so each lands on its own date.
     */
    private int $marks = 0;

    private function service(): AttendanceService
    {
        return app(AttendanceService::class);
    }

    /**
     * One session, stream, subject, class, period and teaching assignment.
     *
     * Everything an attendance row needs a path to, so a test can create the
     * row and nothing else.
     *
     * @return array{session: AcademicSession, class: ClassModel, subject: Subject, period: Period, classSubject: ClassSubject, teacher: User}
     */
    private function structure(?User $teacher = null): array
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

        return compact('session', 'class', 'subject', 'period', 'classSubject', 'teacher');
    }

    /**
     * A student enrolled in the class, so defaulter and summary queries -
     * which walk the current enrollment - can see them.
     *
     * Built with create() rather than the model factory, following the
     * convention in EnrollmentsTest: the factory's roll_number closure is
     * handed an unresolved batch_id and cannot build a row unaided.
     */
    private function enrolledStudent(ClassModel $class, AcademicSession $session): StudentProfile
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
     * One mark per period per day.
     *
     * A second call with the same arguments would move to a later date rather
     * than collide, because the unique index is on (student, class-subject,
     * period, date) - the database will not accept two marks for one student in
     * one period on one day. Dates are only ever read back for filtering, so
     * moving forward keeps a test about percentages from also being a test
     * about dates; pass an explicit $date where the date is the point.
     */
    private function mark(
        StudentProfile $student,
        ClassSubject $classSubject,
        Period $period,
        string $status,
        ?string $date = null,
        ?User $teacher = null,
    ): Attendance {
        return Attendance::create([
            'student_profile_id' => $student->id,
            'class_subject_id' => $classSubject->id,
            'period_id' => $period->id,
            'attendance_date' => $date ?? now()->addDays($this->marks++)->toDateString(),
            'status' => $status,
            'marked_by' => ($teacher ?? $this->createTeacher())->id,
            'marked_at' => now(),
            'notes' => null,
        ]);
    }

    public function test_percentage_is_null_when_no_records(): void
    {
        $s = $this->structure();
        $student = $this->enrolledStudent($s['class'], $s['session']);

        // Null, not zero. A student who has never been marked has an unknown
        // attendance, which is a different statement from an attendance of 0%.
        $this->assertNull(
            $this->service()->percentageForStudent($student->id),
        );
    }

    public function test_percentage_counts_present_as_present(): void
    {
        $s = $this->structure();
        $student = $this->enrolledStudent($s['class'], $s['session']);

        $this->mark($student, $s['classSubject'], $s['period'], Attendance::STATUS_PRESENT);
        $this->mark($student, $s['classSubject'], $s['period'], Attendance::STATUS_PRESENT);
        $this->mark($student, $s['classSubject'], $s['period'], Attendance::STATUS_ABSENT);

        $this->assertEqualsWithDelta(
            66.67,
            $this->service()->percentageForStudent($student->id),
            0.01,
        );
    }

    public function test_percentage_counts_late_as_present(): void
    {
        $s = $this->structure();
        $student = $this->enrolledStudent($s['class'], $s['session']);

        // Three late, one absent: the student was in the room for three of the
        // four periods, having arrived late to each.
        $this->mark($student, $s['classSubject'], $s['period'], Attendance::STATUS_LATE);
        $this->mark($student, $s['classSubject'], $s['period'], Attendance::STATUS_LATE);
        $this->mark($student, $s['classSubject'], $s['period'], Attendance::STATUS_LATE);
        $this->mark($student, $s['classSubject'], $s['period'], Attendance::STATUS_ABSENT);

        $this->assertEqualsWithDelta(
            75.0,
            $this->service()->percentageForStudent($student->id),
            0.01,
        );
    }

    public function test_percentage_counts_leave_as_present(): void
    {
        $s = $this->structure();
        $student = $this->enrolledStudent($s['class'], $s['session']);

        $this->mark($student, $s['classSubject'], $s['period'], Attendance::STATUS_LEAVE);
        $this->mark($student, $s['classSubject'], $s['period'], Attendance::STATUS_ABSENT);

        // Sanctioned leave was not the student's fault, so it counts as
        // attended.
        $this->assertEqualsWithDelta(
            50.0,
            $this->service()->percentageForStudent($student->id),
            0.01,
        );
    }

    public function test_percentage_counts_absent_as_absent(): void
    {
        $s = $this->structure();
        $student = $this->enrolledStudent($s['class'], $s['session']);

        $this->mark($student, $s['classSubject'], $s['period'], Attendance::STATUS_ABSENT);
        $this->mark($student, $s['classSubject'], $s['period'], Attendance::STATUS_ABSENT);

        $this->assertEqualsWithDelta(
            0.0,
            $this->service()->percentageForStudent($student->id),
            0.01,
        );
    }

    public function test_percentage_for_student_filters_by_class_subject_when_given(): void
    {
        $s = $this->structure();

        $other = ClassSubject::create([
            'class_id' => $s['class']->id,
            'subject_id' => $this->createSubject(['name' => 'Physics', 'code' => 'PHY'])->id,
            'teacher_id' => $this->createTeacher()->id,
            'periods_per_week' => 3,
        ]);

        $student = $this->enrolledStudent($s['class'], $s['session']);

        // Three absences in Physics, one present in the first subject.
        $this->mark($student, $other, $s['period'], Attendance::STATUS_ABSENT);
        $this->mark($student, $other, $s['period'], Attendance::STATUS_ABSENT);
        $this->mark($student, $other, $s['period'], Attendance::STATUS_ABSENT);
        $this->mark($student, $s['classSubject'], $s['period'], Attendance::STATUS_PRESENT);

        $this->assertEqualsWithDelta(
            0.0,
            $this->service()->percentageForStudent($student->id, $other->id),
            0.01,
        );

        // Asking for the first subject narrows to the first subject, so the
        // three absences are excluded rather than averaged in.
        $this->assertEqualsWithDelta(
            100.0,
            $this->service()->percentageForStudent($student->id, $s['classSubject']->id),
            0.01,
        );

        // The overall figure still sees all of them.
        $this->assertEqualsWithDelta(
            25.0,
            $this->service()->percentageForStudent($student->id),
            0.01,
        );
    }

    public function test_summary_for_student_returns_overall_and_by_subject(): void
    {
        $s = $this->structure();

        $physics = ClassSubject::create([
            'class_id' => $s['class']->id,
            'subject_id' => $this->createSubject(['name' => 'Physics', 'code' => 'PHY'])->id,
            'teacher_id' => $this->createTeacher()->id,
            'periods_per_week' => 3,
        ]);

        $student = $this->enrolledStudent($s['class'], $s['session']);

        $this->mark($student, $s['classSubject'], $s['period'], Attendance::STATUS_PRESENT);
        $this->mark($student, $s['classSubject'], $s['period'], Attendance::STATUS_ABSENT);
        $this->mark($student, $physics, $s['period'], Attendance::STATUS_PRESENT);

        $summary = app(AttendanceService::class)->summaryForStudent($student->id);

        $this->assertArrayHasKey('overall', $summary);
        $this->assertArrayHasKey('by_subject', $summary);

        // Three marks, two attended, across two subjects.
        $this->assertSame(3, $summary['overall']['total']);
        $this->assertSame(2, $summary['overall']['present']);
        $this->assertEqualsWithDelta(66.67, $summary['overall']['percentage'], 0.01);

        $this->assertCount(2, $summary['by_subject']);

        // Named per subject, so the page can label the rows without a second
        // round trip.
        $names = array_column($summary['by_subject'], 'subject_name');
        sort($names);
        $this->assertSame(['English', 'Physics'], $names);
    }

    public function test_summary_for_student_marks_below_threshold_at_75(): void
    {
        $s = $this->structure();
        $student = $this->enrolledStudent($s['class'], $s['session']);

        // Three attended, one absent: exactly 75%.
        $this->mark($student, $s['classSubject'], $s['period'], Attendance::STATUS_PRESENT);
        $this->mark($student, $s['classSubject'], $s['period'], Attendance::STATUS_PRESENT);
        $this->mark($student, $s['classSubject'], $s['period'], Attendance::STATUS_LATE);
        $this->mark($student, $s['classSubject'], $s['period'], Attendance::STATUS_ABSENT);

        $summary = app(AttendanceService::class)->summaryForStudent($student->id);

        $this->assertEqualsWithDelta(75.0, $summary['overall']['percentage'], 0.01);

        // Exactly at the mark is not below it: the threshold is a minimum,
        // not a target.
        $this->assertFalse($summary['overall']['is_below_threshold']);

        // One more absence drops it below, and the flag flips.
        $this->mark($student, $s['classSubject'], $s['period'], Attendance::STATUS_ABSENT);

        $summary = app(AttendanceService::class)->summaryForStudent($student->id);

        $this->assertEqualsWithDelta(60.0, $summary['overall']['percentage'], 0.01);
        $this->assertTrue($summary['overall']['is_below_threshold']);
    }

    public function test_summary_for_class_returns_all_enrolled_students(): void
    {
        $s = $this->structure();

        $first = $this->enrolledStudent($s['class'], $s['session']);
        $second = $this->enrolledStudent($s['class'], $s['session']);

        $this->mark($first, $s['classSubject'], $s['period'], Attendance::STATUS_PRESENT);

        $summary = app(AttendanceService::class)->summaryForClass($s['class']->id);

        // The roll, not the marks: the second student has never been marked and
        // is still on the register.
        $this->assertCount(2, $summary);

        $ids = array_column($summary, 'student_profile_id');
        $this->assertContains($first->id, $ids);
        $this->assertContains($second->id, $ids);

        $byId = collect($summary)->keyBy('student_profile_id');

        $this->assertEqualsWithDelta(
            100.0,
            $byId[$first->id]['percentage'],
            0.01,
        );

        // The unmarked student is null rather than 0: unknown, not absent.
        $this->assertNull($byId[$second->id]['percentage']);
    }

    public function test_defaulters_excludes_students_at_exactly_75(): void
    {
        $s = $this->structure();
        $student = $this->enrolledStudent($s['class'], $s['session']);

        // Three attended, one absent: exactly the 75% threshold.
        $this->mark($student, $s['classSubject'], $s['period'], Attendance::STATUS_PRESENT);
        $this->mark($student, $s['classSubject'], $s['period'], Attendance::STATUS_PRESENT);
        $this->mark($student, $s['classSubject'], $s['period'], Attendance::STATUS_LATE);
        $this->mark($student, $s['classSubject'], $s['period'], Attendance::STATUS_ABSENT);

        $result = app(AttendanceService::class)->defaulters();

        $this->assertSame(0, $result['total']);
        $this->assertCount(0, $result['students']);
    }

    public function test_defaulters_excludes_students_with_no_records(): void
    {
        $s = $this->structure();

        // Enrolled but never marked. Listing them would name a parent over a
        // problem that does not exist yet.
        $this->enrolledStudent($s['class'], $s['session']);

        $result = app(AttendanceService::class)->defaulters();

        $this->assertSame(0, $result['total']);
        $this->assertCount(0, $result['students']);
    }

    public function test_defaulters_excludes_students_with_null_percentage(): void
    {
        $s = $this->structure();

        $marked = $this->enrolledStudent($s['class'], $s['session']);

        // Two absences and a present: a real 33%, well under the mark.
        $this->mark($marked, $s['classSubject'], $s['period'], Attendance::STATUS_ABSENT);
        $this->mark($marked, $s['classSubject'], $s['period'], Attendance::STATUS_ABSENT);
        $this->mark($marked, $s['classSubject'], $s['period'], Attendance::STATUS_PRESENT);

        // A second student with no marks at all, whose percentage is null.
        $unmarked = $this->enrolledStudent($s['class'], $s['session']);

        $result = app(AttendanceService::class)->defaulters();

        $this->assertSame(1, $result['total']);
        $this->assertCount(1, $result['students']);

        $this->assertSame($marked->id, $result['students'][0]['student_profile_id']);
        $this->assertNotContains(
            $unmarked->id,
            array_column($result['students'], 'student_profile_id'),
        );

        // How far short of the mark the student is, which is what the phone
        // call is about.
        $this->assertEqualsWithDelta(41.67, $result['students'][0]['shortfall'], 0.01);
    }
}
