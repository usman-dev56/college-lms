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

class TimetableTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function teacher(string $name = 'Ahmed Khan'): User
    {
        return User::factory()->create([
            'role' => 'teacher',
            'is_active' => true,
            'name' => $name,
        ]);
    }

    private function academicSession(): AcademicSession
    {
        return AcademicSession::create([
            'name' => '2026-2027',
            'start_date' => '2026-08-01',
            'end_date' => '2027-05-31',
            'is_active' => true,
        ]);
    }

    /**
     * The shared Pre-Medical stream. Created once per test, because the code
     * is unique.
     */
    private function stream(): Stream
    {
        return Stream::firstOrCreate(
            ['code' => 'PM'],
            ['name' => 'Pre-Medical', 'is_active' => true]
        );
    }

    private function classModel(
        ?AcademicSession $session = null,
        string $section = 'A'
    ): ClassModel {
        return ClassModel::create([
            'academic_session_id' => ($session ?? $this->academicSession())->id,
            'stream_id' => $this->stream()->id,
            'grade_level' => 11,
            'section' => $section,
            'capacity' => 50,
            'room' => 'R-101',
            'is_active' => true,
        ]);
    }

    private function subject(string $name = 'Physics'): Subject
    {
        return Subject::create([
            'name' => $name,
            'code' => 'PHY',
            'grade_level' => 11,
            'stream_id' => null,
            'is_active' => true,
        ]);
    }

    private function period(
        AcademicSession $session,
        int $number = 1,
        bool $isBreak = false
    ): Period {
        $starts = 8 * 60 + ($number - 1) * 45;

        return Period::create([
            'academic_session_id' => $session->id,
            'number' => $number,
            'label' => $isBreak ? 'Break' : 'Period '.$number,
            'start_time' => sprintf('%02d:%02d', intdiv($starts, 60), $starts % 60),
            'end_time' => sprintf(
                '%02d:%02d',
                intdiv($starts + 40, 60),
                ($starts + 40) % 60
            ),
            'is_break' => $isBreak,
        ]);
    }

    private function assignment(
        ClassModel $class,
        User $teacher,
        string $name = 'Physics',
        int $periodsPerWeek = 2
    ): ClassSubject {
        return ClassSubject::create([
            'class_id' => $class->id,
            'subject_id' => $this->subject($name)->id,
            'teacher_id' => $teacher->id,
            'periods_per_week' => $periodsPerWeek,
        ]);
    }

    /**
     * A payload that schedules every subject exactly its weekly allocation,
     * spreading the cells across days and teaching periods.
     *
     * @param  array<int, ClassSubject>  $assignments
     * @param  array<int, Period>  $periods
     * @return array<string, mixed>
     */
    private function balancedPayload(
        array $assignments,
        array $periods
    ): array {
        $slots = [];
        $day = 1;

        foreach ($assignments as $assignment) {
            for ($i = 0; $i < $assignment->periods_per_week; $i++) {
                $slots[] = [
                    'day_of_week' => $day,
                    'period_id' => $periods[$i % count($periods)]->id,
                    'class_subject_id' => $assignment->id,
                ];
                $day = $day % 6 + 1;
            }
        }

        return ['slots' => $slots];
    }

    public function test_edit_page_renders_the_grid_inputs(): void
    {
        $session = $this->academicSession();
        $class = $this->classModel($session);
        $teacher = $this->teacher();
        $assignment = $this->assignment($class, $teacher, 'Physics', 2);
        $this->period($session, 1);
        $this->period($session, 2);

        $this->actingAs($this->admin())
            ->get("/admin/classes/{$class->id}/timetable")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Timetable/Edit')
                ->where('class.display_name', '11th Pre-Medical A')
                ->where('days.1', 'Monday')
                ->where('days.6', 'Saturday')
                ->has('periods', 2)
                ->where('periods.0.start_time', '08:00')
                ->has('classSubjects', 1)
                ->where('classSubjects.0.subject_name', 'Physics')
                ->where('classSubjects.0.teacher_name', 'Ahmed Khan')
                // Nothing scheduled yet, so the whole allocation is left.
                ->where('remaining.'.$assignment->id, 2)
                ->has('existingSlots', 0));
    }

    public function test_update_saves_a_balanced_week(): void
    {
        $session = $this->academicSession();
        $class = $this->classModel($session);
        $teacher = $this->teacher();
        $physics = $this->assignment($class, $teacher, 'Physics', 2);
        $english = $this->assignment(
            $class,
            $this->teacher('Bilal Ahmad'),
            'English',
            1
        );

        $periods = [$this->period($session, 1), $this->period($session, 2)];

        $this->actingAs($this->admin())
            ->put(
                "/admin/classes/{$class->id}/timetable",
                $this->balancedPayload([$physics, $english], $periods)
            )
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.classes.timetable.edit', $class));

        $this->assertSame(3, TimetableSlot::where('class_id', $class->id)->count());
        $this->assertDatabaseHas('timetable_slots', [
            'class_id' => $class->id,
            'class_subject_id' => $physics->id,
            'day_of_week' => 1,
            'period_id' => $periods[0]->id,
        ]);

        // The room is not part of the UI yet, so it stays null.
        $this->assertNull(TimetableSlot::first()->room);
    }

    public function test_resaving_soft_deletes_the_previous_grid(): void
    {
        $session = $this->academicSession();
        $class = $this->classModel($session);
        $teacher = $this->teacher();
        $assignment = $this->assignment($class, $teacher, 'Physics', 2);
        $periods = [$this->period($session, 1), $this->period($session, 2)];

        $this->actingAs($this->admin())
            ->put(
                "/admin/classes/{$class->id}/timetable",
                $this->balancedPayload([$assignment], $periods)
            )
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin())
            ->put(
                "/admin/classes/{$class->id}/timetable",
                $this->balancedPayload([$assignment], $periods)
            )
            ->assertSessionHasNoErrors();

        // Two saves of a two-period week: two live rows, and the first two
        // kept on record as soft deleted.
        $this->assertSame(2, TimetableSlot::where('class_id', $class->id)->count());
        $this->assertSame(
            4,
            TimetableSlot::withTrashed()->where('class_id', $class->id)->count()
        );
    }

    public function test_update_rejects_an_incomplete_week(): void
    {
        $session = $this->academicSession();
        $class = $this->classModel($session);
        $teacher = $this->teacher();
        $assignment = $this->assignment($class, $teacher, 'Physics', 3);
        $period = $this->period($session, 1);

        $this->actingAs($this->admin())
            ->from("/admin/classes/{$class->id}/timetable")
            ->put("/admin/classes/{$class->id}/timetable", [
                'slots' => [
                    [
                        'day_of_week' => 1,
                        'period_id' => $period->id,
                        'class_subject_id' => $assignment->id,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('timetable_slots', 0);
    }

    public function test_update_rejects_a_break_period(): void
    {
        $session = $this->academicSession();
        $class = $this->classModel($session);
        $teacher = $this->teacher();
        $assignment = $this->assignment($class, $teacher, 'Physics', 1);
        $break = $this->period($session, 4, true);

        $this->actingAs($this->admin())
            ->from("/admin/classes/{$class->id}/timetable")
            ->put("/admin/classes/{$class->id}/timetable", [
                'slots' => [[
                    'day_of_week' => 1,
                    'period_id' => $break->id,
                    'class_subject_id' => $assignment->id,
                ]],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('timetable_slots', 0);
    }

    public function test_update_rejects_a_subject_from_another_class(): void
    {
        $session = $this->academicSession();
        $class = $this->classModel($session);
        $otherClass = $this->classModel($session, 'B');
        $teacher = $this->teacher();
        $foreign = $this->assignment($otherClass, $teacher, 'English', 1);
        $period = $this->period($session, 1);

        $this->actingAs($this->admin())
            ->from("/admin/classes/{$class->id}/timetable")
            ->put("/admin/classes/{$class->id}/timetable", [
                'slots' => [[
                    'day_of_week' => 1,
                    'period_id' => $period->id,
                    'class_subject_id' => $foreign->id,
                ]],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('timetable_slots', 0);
    }

    /**
     * A second section of the same stream and session, for cross-class checks.
     */
    private function secondSection(ClassModel $first): ClassModel
    {
        return ClassModel::create([
            'academic_session_id' => $first->academic_session_id,
            'stream_id' => $first->stream_id,
            'grade_level' => $first->grade_level,
            'section' => 'B',
            'capacity' => 50,
            'room' => 'R-102',
            'is_active' => true,
        ]);
    }

    public function test_a_teacher_cannot_be_booked_twice_at_the_same_time(): void
    {
        $session = $this->academicSession();
        $classA = $this->classModel($session);
        $classB = $this->secondSection($classA);

        $teacher = $this->teacher();
        $period = $this->period($session, 1);

        $inA = $this->assignment($classA, $teacher, 'Physics', 1);
        $inB = $this->assignment($classB, $teacher, 'Chemistry', 1);

        $this->actingAs($this->admin())
            ->put("/admin/classes/{$classA->id}/timetable", [
                'slots' => [[
                    'day_of_week' => 1,
                    'period_id' => $period->id,
                    'class_subject_id' => $inA->id,
                ]],
            ])
            ->assertSessionHasNoErrors();

        // The same teacher, same day and period, different class.
        $this->actingAs($this->admin())
            ->from("/admin/classes/{$classB->id}/timetable")
            ->put("/admin/classes/{$classB->id}/timetable", [
                'slots' => [[
                    'day_of_week' => 1,
                    'period_id' => $period->id,
                    'class_subject_id' => $inB->id,
                ]],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('timetable_slots', 1);
    }

    public function test_the_same_teacher_may_teach_two_classes_at_different_times(): void
    {
        $session = $this->academicSession();
        $classA = $this->classModel($session);
        $classB = $this->secondSection($classA);

        $teacher = $this->teacher();
        $period = $this->period($session, 1);

        $inA = $this->assignment($classA, $teacher, 'Physics', 1);
        $inB = $this->assignment($classB, $teacher, 'Chemistry', 1);

        $this->actingAs($this->admin())
            ->put("/admin/classes/{$classA->id}/timetable", [
                'slots' => [[
                    'day_of_week' => 1,
                    'period_id' => $period->id,
                    'class_subject_id' => $inA->id,
                ]],
            ])
            ->assertSessionHasNoErrors();

        // Tuesday instead of Monday: no clash.
        $this->actingAs($this->admin())
            ->put("/admin/classes/{$classB->id}/timetable", [
                'slots' => [[
                    'day_of_week' => 2,
                    'period_id' => $period->id,
                    'class_subject_id' => $inB->id,
                ]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('timetable_slots', 2);
    }

    public function test_rebuilding_a_class_ignores_its_own_existing_slots(): void
    {
        $session = $this->academicSession();
        $class = $this->classModel($session);
        $teacher = $this->teacher();
        $assignment = $this->assignment($class, $teacher, 'Physics', 1);
        $period = $this->period($session, 1);

        $payload = ['slots' => [[
            'day_of_week' => 1,
            'period_id' => $period->id,
            'class_subject_id' => $assignment->id,
        ]]];

        $this->actingAs($this->admin())
            ->put("/admin/classes/{$class->id}/timetable", $payload)
            ->assertSessionHasNoErrors();

        // Saving the identical grid again must not report a conflict with
        // itself: the class being edited is excluded from the check.
        $this->actingAs($this->admin())
            ->put("/admin/classes/{$class->id}/timetable", $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame(1, TimetableSlot::where('class_id', $class->id)->count());
    }

    public function test_show_page_resolves_subject_and_teacher_names(): void
    {
        $session = $this->academicSession();
        $class = $this->classModel($session);
        $teacher = $this->teacher();
        $assignment = $this->assignment($class, $teacher, 'Physics', 1);
        $period = $this->period($session, 1);

        $this->actingAs($this->admin())
            ->put("/admin/classes/{$class->id}/timetable", [
                'slots' => [[
                    'day_of_week' => 1,
                    'period_id' => $period->id,
                    'class_subject_id' => $assignment->id,
                ]],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin())
            ->get("/admin/classes/{$class->id}/timetable/view")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Timetable/Show')
                ->has('slots', 1)
                ->where('slots.0.subject_name', 'Physics')
                ->where('slots.0.teacher_name', 'Ahmed Khan')
                ->where('slots.0.day_of_week', 1));
    }

    public function test_non_admins_are_blocked(): void
    {
        $session = $this->academicSession();
        $class = $this->classModel($session);
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($teacher)
            ->get("/admin/classes/{$class->id}/timetable")
            ->assertForbidden();
        $this->actingAs($teacher)
            ->get("/admin/classes/{$class->id}/timetable/view")
            ->assertForbidden();
        $this->actingAs($teacher)
            ->put("/admin/classes/{$class->id}/timetable", ['slots' => []])
            ->assertForbidden();

        $this->assertDatabaseCount('timetable_slots', 0);
    }
}
