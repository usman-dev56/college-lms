<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\ClassModel;
use App\Models\ClassSubject;
use App\Models\Stream;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClassSubjectsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function teacher(): User
    {
        return User::factory()->create([
            'role' => 'teacher',
            'is_active' => true,
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

    private function stream(string $name = 'Pre-Medical', string $code = 'PM'): Stream
    {
        return Stream::create([
            'name' => $name,
            'code' => $code,
            'is_active' => true,
        ]);
    }

    private function classModel(Stream $stream, int $grade = 11): ClassModel
    {
        return ClassModel::create([
            'academic_session_id' => $this->academicSession()->id,
            'stream_id' => $stream->id,
            'grade_level' => $grade,
            'section' => 'A',
            'capacity' => 50,
            'room' => 'R-101',
            'is_active' => true,
        ]);
    }

    private function subject(string $name, int $grade, ?Stream $stream = null): Subject
    {
        return Subject::create([
            'name' => $name,
            'code' => 'SUB',
            'grade_level' => $grade,
            'stream_id' => $stream?->id,
            'is_active' => true,
        ]);
    }

    public function test_edit_page_lists_available_subjects_and_teachers(): void
    {
        $stream = $this->stream();
        $class = $this->classModel($stream);
        $teacher = $this->teacher();

        $compulsory = $this->subject('English', 11);
        $elective = $this->subject('Biology', 11, $stream);

        // Neither can be taught here: wrong grade level, and another stream.
        $this->subject('Urdu', 12);
        $this->subject('Physics', 11, $this->stream('ICS', 'ICS'));

        $this->actingAs($this->admin())
            ->get("/admin/classes/{$class->id}/assignments")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Classes/Assignments')
                ->where('class.display_name', '11th Pre-Medical A')
                ->where('class.grade_level', 11)
                ->where('class.stream.code', 'PM')
                ->has('subjects', 2)
                ->where('subjects.0.id', $compulsory->id)
                ->where('subjects.1.id', $elective->id)
                ->where('teachers.0.name', $teacher->name)
                ->has('existingAssignments', 0));
    }

    public function test_update_creates_assignments(): void
    {
        $stream = $this->stream();
        $class = $this->classModel($stream);
        $teacher = $this->teacher();
        $english = $this->subject('English', 11);
        $biology = $this->subject('Biology', 11, $stream);

        $this->actingAs($this->admin())
            ->put("/admin/classes/{$class->id}/assignments", [
                'assignments' => [
                    [
                        'subject_id' => $english->id,
                        'teacher_id' => $teacher->id,
                        'periods_per_week' => 6,
                    ],
                    [
                        'subject_id' => $biology->id,
                        'teacher_id' => $teacher->id,
                        'periods_per_week' => 7,
                    ],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.classes.assignments.edit', $class));

        $this->assertDatabaseHas('class_subjects', [
            'class_id' => $class->id,
            'subject_id' => $english->id,
            'teacher_id' => $teacher->id,
            'periods_per_week' => 6,
        ]);

        $this->assertDatabaseHas('class_subjects', [
            'class_id' => $class->id,
            'subject_id' => $biology->id,
            'periods_per_week' => 7,
        ]);
    }

    public function test_update_changes_the_teacher_of_an_existing_assignment(): void
    {
        $stream = $this->stream();
        $class = $this->classModel($stream);
        $english = $this->subject('English', 11);
        $firstTeacher = $this->teacher();
        $secondTeacher = $this->teacher();

        ClassSubject::create([
            'class_id' => $class->id,
            'subject_id' => $english->id,
            'teacher_id' => $firstTeacher->id,
            'periods_per_week' => 5,
        ]);

        $this->actingAs($this->admin())
            ->put("/admin/classes/{$class->id}/assignments", [
                'assignments' => [
                    [
                        'subject_id' => $english->id,
                        'teacher_id' => $secondTeacher->id,
                        'periods_per_week' => 6,
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        // The same row is updated, not a second one added.
        $this->assertDatabaseCount('class_subjects', 1);

        $this->assertDatabaseHas('class_subjects', [
            'class_id' => $class->id,
            'subject_id' => $english->id,
            'teacher_id' => $secondTeacher->id,
            'periods_per_week' => 6,
        ]);
    }

    public function test_omitted_subject_is_soft_deleted_and_restored_when_added_back(): void
    {
        $stream = $this->stream();
        $class = $this->classModel($stream);
        $teacher = $this->teacher();
        $english = $this->subject('English', 11);
        $urdu = $this->subject('Urdu', 11);

        ClassSubject::create([
            'class_id' => $class->id,
            'subject_id' => $english->id,
            'teacher_id' => $teacher->id,
            'periods_per_week' => 6,
        ]);

        $removed = ClassSubject::create([
            'class_id' => $class->id,
            'subject_id' => $urdu->id,
            'teacher_id' => $teacher->id,
            'periods_per_week' => 5,
        ]);

        // Saving without Urdu takes it out of the class.
        $this->actingAs($this->admin())
            ->put("/admin/classes/{$class->id}/assignments", [
                'assignments' => [
                    [
                        'subject_id' => $english->id,
                        'teacher_id' => $teacher->id,
                        'periods_per_week' => 6,
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted('class_subjects', ['id' => $removed->id]);
        $this->assertSame(1, $class->subjects()->count());

        // Adding it back restores that same row instead of duplicating it.
        $this->actingAs($this->admin())
            ->put("/admin/classes/{$class->id}/assignments", [
                'assignments' => [
                    [
                        'subject_id' => $english->id,
                        'teacher_id' => $teacher->id,
                        'periods_per_week' => 6,
                    ],
                    [
                        'subject_id' => $urdu->id,
                        'teacher_id' => $teacher->id,
                        'periods_per_week' => 5,
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('class_subjects', 2);
        $this->assertDatabaseHas('class_subjects', [
            'id' => $removed->id,
            'deleted_at' => null,
        ]);
    }

    public function test_update_rejects_a_user_who_is_not_a_teacher(): void
    {
        $stream = $this->stream();
        $class = $this->classModel($stream);
        $english = $this->subject('English', 11);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($this->admin())
            ->from("/admin/classes/{$class->id}/assignments")
            ->put("/admin/classes/{$class->id}/assignments", [
                'assignments' => [
                    [
                        'subject_id' => $english->id,
                        'teacher_id' => $student->id,
                        'periods_per_week' => 5,
                    ],
                ],
            ])
            ->assertSessionHasErrors('assignments.0.teacher_id');

        $this->assertDatabaseCount('class_subjects', 0);
    }

    public function test_update_rejects_a_subject_from_another_grade_or_stream(): void
    {
        $stream = $this->stream();
        $class = $this->classModel($stream);
        $teacher = $this->teacher();

        $otherGrade = $this->subject('English', 12);
        $otherStream = $this->subject('Physics', 11, $this->stream('ICS', 'ICS'));

        foreach ([$otherGrade, $otherStream] as $subject) {
            $this->actingAs($this->admin())
                ->from("/admin/classes/{$class->id}/assignments")
                ->put("/admin/classes/{$class->id}/assignments", [
                    'assignments' => [
                        [
                            'subject_id' => $subject->id,
                            'teacher_id' => $teacher->id,
                            'periods_per_week' => 5,
                        ],
                    ],
                ])
                ->assertSessionHasErrors('assignments');

            $this->assertDatabaseCount('class_subjects', 0);
        }
    }

    public function test_periods_per_week_must_stay_within_range(): void
    {
        $stream = $this->stream();
        $class = $this->classModel($stream);
        $teacher = $this->teacher();
        $english = $this->subject('English', 11);

        $this->actingAs($this->admin())
            ->from("/admin/classes/{$class->id}/assignments")
            ->put("/admin/classes/{$class->id}/assignments", [
                'assignments' => [
                    [
                        'subject_id' => $english->id,
                        'teacher_id' => $teacher->id,
                        'periods_per_week' => 21,
                    ],
                ],
            ])
            ->assertSessionHasErrors('assignments.0.periods_per_week');

        $this->assertDatabaseCount('class_subjects', 0);
    }

    public function test_a_subject_cannot_be_assigned_twice_to_the_same_class(): void
    {
        $stream = $this->stream();
        $class = $this->classModel($stream);
        $teacher = $this->teacher();
        $english = $this->subject('English', 11);

        $this->actingAs($this->admin())
            ->from("/admin/classes/{$class->id}/assignments")
            ->put("/admin/classes/{$class->id}/assignments", [
                'assignments' => [
                    [
                        'subject_id' => $english->id,
                        'teacher_id' => $teacher->id,
                        'periods_per_week' => 6,
                    ],
                    [
                        'subject_id' => $english->id,
                        'teacher_id' => $teacher->id,
                        'periods_per_week' => 5,
                    ],
                ],
            ])
            ->assertSessionHasErrors('assignments.1.subject_id');

        $this->assertDatabaseCount('class_subjects', 0);
    }

    public function test_relationships_connect_teacher_class_and_subject(): void
    {
        $stream = $this->stream();
        $class = $this->classModel($stream);
        $teacher = $this->teacher();
        $english = $this->subject('English', 11);

        $assignment = ClassSubject::create([
            'class_id' => $class->id,
            'subject_id' => $english->id,
            'teacher_id' => $teacher->id,
            'periods_per_week' => 6,
        ]);

        $this->assertTrue($teacher->teacherAssignments->contains($assignment));
        $this->assertTrue($assignment->teacher->is($teacher));
        $this->assertTrue($assignment->classModel->is($class));
        $this->assertTrue($assignment->subject->is($english));
        $this->assertTrue($class->classSubjects->contains($assignment));
        $this->assertTrue($english->classSubjects->contains($assignment));
    }

    public function test_non_admins_are_blocked(): void
    {
        $stream = $this->stream();
        $class = $this->classModel($stream);
        $teacher = $this->teacher();
        $english = $this->subject('English', 11);

        $this->actingAs($teacher)
            ->get("/admin/classes/{$class->id}/assignments")
            ->assertForbidden();

        $this->actingAs($teacher)
            ->put("/admin/classes/{$class->id}/assignments", [
                'assignments' => [
                    [
                        'subject_id' => $english->id,
                        'teacher_id' => $teacher->id,
                        'periods_per_week' => 5,
                    ],
                ],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('class_subjects', 0);
    }
}
