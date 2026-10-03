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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The office's side of the register: browsing it, correcting it, and deleting
 * a mark created in error.
 *
 * Every write here leaves an audit row. That is the whole point of the page -
 * an attendance record is evidence, so a correction cannot be silent and a
 * deletion cannot be a hard delete.
 */
class AdminAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $today;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->today = Carbon::parse('2026-09-21')->startOfDay();
        Carbon::setTestNow($this->today);
        $this->admin = $this->createAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * One session, class, period, teacher and teaching assignment, plus two
     * enrolled students.
     *
     * @return array{session: AcademicSession, class: ClassModel, period: Period, classSubject: ClassSubject, first: StudentProfile, second: StudentProfile}
     */
    private function register(): array
    {
        $session = $this->createActiveSession();
        $stream = $this->createStream();
        $class = $this->createClass($session, $stream);
        $period = $this->createPeriod($session);
        $teacher = $this->createTeacher();

        $classSubject = ClassSubject::create([
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

            $students[] = $student;
        }

        return [
            'session' => $session,
            'class' => $class,
            'period' => $period,
            'classSubject' => $classSubject,
            'first' => $students[0],
            'second' => $students[1],
        ];
    }

    /**
     * One marked period.
     */
    private function mark(array $s, StudentProfile $student, string $status = Attendance::STATUS_PRESENT): Attendance
    {
        return Attendance::create([
            'student_profile_id' => $student->id,
            'class_subject_id' => $s['classSubject']->id,
            'period_id' => $s['period']->id,
            'attendance_date' => $this->today->toDateString(),
            'status' => $status,
            'marked_by' => $this->createTeacher()->id,
            'marked_at' => now(),
            'notes' => null,
        ]);
    }

    public function test_admin_can_list_attendance_records(): void
    {
        $s = $this->register();
        $this->mark($s, $s['first']);
        $this->mark($s, $s['second'], Attendance::STATUS_ABSENT);

        $this->actingAs($this->admin)
            ->get(route('admin.attendance.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Attendance/Index')
                ->has('records.data', 2)
                // The badge counts the whole result, not the page, so the two
                // numbers on the screen agree.
                ->where('counts.total', 2)
                ->where('counts.present', 1)
                ->where('counts.absent', 1)
                ->where('counts.late', 0)
                ->where('counts.leave', 0)
                ->has('classes')
                ->has('filters')
            );
    }

    public function test_admin_can_filter_attendance_by_class(): void
    {
        $s = $this->register();

        // A second class with its own subject, period and record, so the
        // filter has something to exclude.
        $other = ClassModel::create([
            'academic_session_id' => $s['session']->id,
            'stream_id' => $s['class']->stream_id,
            'grade_level' => 12,
            'section' => 'B',
            'capacity' => 40,
            'room' => null,
            'is_active' => true,
        ]);

        $otherPeriod = $this->createPeriod($s['session'], [
            'number' => 5,
            'label' => 'Period 5',
            'start_time' => '11:00',
            'end_time' => '11:45',
        ]);

        $otherSubject = ClassSubject::create([
            'class_id' => $other->id,
            'subject_id' => $this->createSubject(['name' => 'Physics', 'code' => 'PHY'])->id,
            'teacher_id' => $this->createTeacher()->id,
            'periods_per_week' => 3,
        ]);

        $this->mark($s, $s['first']);
        $this->mark($s, $s['second'], Attendance::STATUS_ABSENT);

        Attendance::create([
            'student_profile_id' => $s['first']->id,
            'class_subject_id' => $otherSubject->id,
            'period_id' => $otherPeriod->id,
            'attendance_date' => $this->today->toDateString(),
            'status' => Attendance::STATUS_PRESENT,
            'marked_by' => $this->createTeacher()->id,
            'marked_at' => now(),
            'notes' => null,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.attendance.index', ['class_id' => $s['class']->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('records.data', 2)
                ->where('counts.total', 2)
                ->where('filters.class_id', $s['class']->id)
            );
    }

    public function test_admin_can_filter_attendance_by_date(): void
    {
        $s = $this->register();
        $this->mark($s, $s['first']);

        // A mark from yesterday, outside the filtered date.
        Attendance::create([
            'student_profile_id' => $s['second']->id,
            'class_subject_id' => $s['classSubject']->id,
            'period_id' => $s['period']->id,
            'attendance_date' => $this->today->copy()->subDay()->toDateString(),
            'status' => Attendance::STATUS_ABSENT,
            'marked_by' => $this->createTeacher()->id,
            'marked_at' => now(),
            'notes' => null,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.attendance.index', ['date' => $this->today->toDateString()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('records.data', 1)
                ->where('counts.total', 1)
                ->where('records.data.0.student.roll_number', $s['first']->roll_number)
                ->where('filters.date', $this->today->toDateString())
            );
    }

    public function test_admin_can_filter_attendance_by_status(): void
    {
        $s = $this->register();
        $this->mark($s, $s['first']);
        $this->mark($s, $s['second'], Attendance::STATUS_ABSENT);

        $this->actingAs($this->admin)
            ->get(route('admin.attendance.index', ['status' => Attendance::STATUS_ABSENT]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('records.data', 1)
                ->where('counts.total', 1)
                ->where('counts.absent', 1)
                ->where('filters.status', Attendance::STATUS_ABSENT)
            );

        // An unknown status is ignored rather than emptying the page.
        $this->actingAs($this->admin)
            ->get(route('admin.attendance.index', ['status' => 'nonsense']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('records.data', 2)
                ->where('filters.status', 'all')
            );
    }

    public function test_admin_can_view_a_single_attendance_record(): void
    {
        $s = $this->register();
        $attendance = $this->mark($s, $s['first'], Attendance::STATUS_LATE);

        $this->actingAs($this->admin)
            ->get(route('admin.attendance.show', $attendance))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Attendance/Show')
                ->where('record.id', $attendance->id)
                ->where('record.status', Attendance::STATUS_LATE)
                ->where('record.attendance_date', $this->today->toDateString())
                ->where('record.student.id', $s['first']->id)
                ->where('record.student.roll_number', $s['first']->roll_number)
                // The whole trail, so a disputed mark can be traced.
                ->has('record.audits', 0)
            );
    }

    public function test_admin_can_update_a_status_and_it_creates_an_audit(): void
    {
        $s = $this->register();
        $attendance = $this->mark($s, $s['first'], Attendance::STATUS_ABSENT);

        $this->actingAs($this->admin)
            ->patch(route('admin.attendance.update', $attendance), [
                'status' => Attendance::STATUS_PRESENT,
                'reason' => 'Register was mislaid; confirmed with the student.',
            ])
            ->assertRedirect(route('admin.attendance.show', $attendance))
            ->assertSessionHas('success');

        $this->assertSame(Attendance::STATUS_PRESENT, $attendance->fresh()->status);

        $this->assertDatabaseHas('attendance_audits', [
            'attendance_id' => $attendance->id,
            'edited_by' => $this->admin->id,
            'old_status' => Attendance::STATUS_ABSENT,
            'new_status' => Attendance::STATUS_PRESENT,
            'reason' => 'Register was mislaid; confirmed with the student.',
        ]);
    }

    public function test_admin_update_fails_when_status_is_unchanged(): void
    {
        $s = $this->register();
        $attendance = $this->mark($s, $s['first'], Attendance::STATUS_ABSENT);

        $this->actingAs($this->admin)
            ->patch(route('admin.attendance.update', $attendance), [
                'status' => Attendance::STATUS_ABSENT,
                'reason' => 'No change.',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(Attendance::STATUS_ABSENT, $attendance->fresh()->status);

        // No audit, because nothing changed: a trail describing an edit that
        // never happened is worse than no trail.
        $this->assertDatabaseCount('attendance_audits', 0);
    }

    public function test_admin_can_bulk_update_multiple_records(): void
    {
        $s = $this->register();
        $first = $this->mark($s, $s['first'], Attendance::STATUS_ABSENT);
        $second = $this->mark($s, $s['second'], Attendance::STATUS_ABSENT);

        $this->actingAs($this->admin)
            ->post(route('admin.attendance.bulk-update'), [
                'class_subject_id' => $s['classSubject']->id,
                'period_id' => $s['period']->id,
                'attendance_date' => $this->today->toDateString(),
                'reason' => 'Whole register entered against the wrong column.',
                'updates' => [
                    ['attendance_id' => $first->id, 'status' => Attendance::STATUS_PRESENT],
                    ['attendance_id' => $second->id, 'status' => Attendance::STATUS_PRESENT],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(Attendance::STATUS_PRESENT, $first->fresh()->status);
        $this->assertSame(Attendance::STATUS_PRESENT, $second->fresh()->status);

        // One audit per changed row, all carrying the batch reason.
        $this->assertDatabaseCount('attendance_audits', 2);
        $this->assertDatabaseHas('attendance_audits', [
            'attendance_id' => $first->id,
            'old_status' => Attendance::STATUS_ABSENT,
            'new_status' => Attendance::STATUS_PRESENT,
            'reason' => 'Whole register entered against the wrong column.',
        ]);
    }

    public function test_bulk_update_rejects_records_from_a_different_class_period_date(): void
    {
        $s = $this->register();
        $inScope = $this->mark($s, $s['first'], Attendance::STATUS_ABSENT);

        // A record from a different period, which the payload does not name.
        $otherPeriod = $this->createPeriod($s['session'], [
            'number' => 6,
            'label' => 'Period 6',
            'start_time' => '12:00',
            'end_time' => '12:45',
        ]);

        $outOfScope = Attendance::create([
            'student_profile_id' => $s['second']->id,
            'class_subject_id' => $s['classSubject']->id,
            'period_id' => $otherPeriod->id,
            'attendance_date' => $this->today->toDateString(),
            'status' => Attendance::STATUS_ABSENT,
            'marked_by' => $this->createTeacher()->id,
            'marked_at' => now(),
            'notes' => null,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.attendance.bulk-update'), [
                'class_subject_id' => $s['classSubject']->id,
                'period_id' => $s['period']->id,
                'attendance_date' => $this->today->toDateString(),
                'reason' => 'Attempting to rewrite another period.',
                'updates' => [
                    ['attendance_id' => $inScope->id, 'status' => Attendance::STATUS_PRESENT],
                    ['attendance_id' => $outOfScope->id, 'status' => Attendance::STATUS_PRESENT],
                ],
            ])
            ->assertRedirect();

        // The in-scope row moves; the out-of-scope row is untouched, so a
        // stale tab cannot rewrite a period it was not looking at.
        $this->assertSame(Attendance::STATUS_PRESENT, $inScope->fresh()->status);
        $this->assertSame(Attendance::STATUS_ABSENT, $outOfScope->fresh()->status);

        $this->assertDatabaseCount('attendance_audits', 1);
        $this->assertDatabaseMissing('attendance_audits', [
            'attendance_id' => $outOfScope->id,
        ]);
    }

    public function test_admin_can_delete_an_attendance_record_with_a_reason(): void
    {
        $s = $this->register();
        $attendance = $this->mark($s, $s['first'], Attendance::STATUS_ABSENT);

        $this->actingAs($this->admin)
            ->delete(route('admin.attendance.destroy', $attendance), [
                'reason' => 'Entered against the wrong class.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        // Soft deleted: the row still exists, it has just left the roll. It
        // may still be evidence.
        $this->assertSoftDeleted('attendances', [
            'id' => $attendance->id,
        ]);
    }

    public function test_delete_creates_an_audit_with_status_deleted(): void
    {
        $s = $this->register();
        $attendance = $this->mark($s, $s['first'], Attendance::STATUS_ABSENT);

        // A reason is mandatory, and the check runs before anything is
        // written: a deletion nobody can explain is indistinguishable from one
        // nobody should have made.
        $this->actingAs($this->admin)
            ->delete(route('admin.attendance.destroy', $attendance), [])
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseCount('attendance_audits', 0);
        $this->assertNotSoftDeleted('attendances', ['id' => $attendance->id]);

        $this->actingAs($this->admin)
            ->delete(route('admin.attendance.destroy', $attendance), [
                'reason' => 'Entered against the wrong class.',
            ])
            ->assertSessionHas('success');

        // 'deleted' is not a mark the teacher can set - it is the terminal
        // state of the record, recorded so the deletion is never silent.
        $this->assertDatabaseHas('attendance_audits', [
            'attendance_id' => $attendance->id,
            'edited_by' => $this->admin->id,
            'old_status' => Attendance::STATUS_ABSENT,
            'new_status' => 'deleted',
            'reason' => 'Entered against the wrong class.',
        ]);
    }

    public function test_non_admin_cannot_access_admin_attendance(): void
    {
        $s = $this->register();
        $attendance = $this->mark($s, $s['first']);

        $teacher = $this->createTeacher();
        $student = $this->createStudent();

        foreach ([$teacher, $student] as $user) {
            $this->actingAs($user)
                ->get(route('admin.attendance.index'))
                ->assertForbidden();

            $this->actingAs($user)
                ->get(route('admin.attendance.show', $attendance))
                ->assertForbidden();

            $this->actingAs($user)
                ->patch(route('admin.attendance.update', $attendance), [
                    'status' => Attendance::STATUS_PRESENT,
                    'reason' => 'Not allowed.',
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->delete(route('admin.attendance.destroy', $attendance), [
                    'reason' => 'Not allowed.',
                ])
                ->assertForbidden();
        }

        // Nothing was written by any of them.
        $this->assertSame(Attendance::STATUS_PRESENT, $attendance->fresh()->status);
        $this->assertDatabaseCount('attendance_audits', 0);
    }
}
