<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\ClassModel;
use App\Models\ClassSubject;
use App\Models\Stream;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeachersTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function teacher(string $name = 'Ahmed Khan', array $attributes = []): User
    {
        return User::factory()->create([
            'role' => 'teacher',
            'is_active' => true,
            'name' => $name,
            ...$attributes,
        ]);
    }

    private function subject(string $name = 'Physics', int $grade = 11): Subject
    {
        return Subject::create([
            'name' => $name,
            'code' => 'PHY',
            'grade_level' => $grade,
            'stream_id' => null,
            'is_active' => true,
        ]);
    }

    private function classModel(): ClassModel
    {
        $session = AcademicSession::create([
            'name' => '2026-2027',
            'start_date' => '2026-08-01',
            'end_date' => '2027-05-31',
            'is_active' => true,
        ]);

        $stream = Stream::create([
            'name' => 'Pre-Medical',
            'code' => 'PM',
            'is_active' => true,
        ]);

        return ClassModel::create([
            'academic_session_id' => $session->id,
            'stream_id' => $stream->id,
            'grade_level' => 11,
            'section' => 'A',
            'capacity' => 50,
            'room' => 'R-101',
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'name' => 'Test Teacher',
            'email' => 'test.teacher@college.test',
            'phone' => '0300-1234567',
            'password' => 'TestPass123',
            'password_confirmation' => 'TestPass123',
            'is_active' => true,
            ...$overrides,
        ];
    }

    public function test_index_lists_only_teachers_with_their_subjects(): void
    {
        $teacher = $this->teacher('Ahmed Khan');
        $subject = $this->subject('Physics');
        $class = $this->classModel();

        ClassSubject::create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'periods_per_week' => 7,
        ]);

        // Other roles must never appear in the teachers list.
        $this->admin();
        User::factory()->create(['role' => 'student', 'name' => 'Zoe Student']);

        $this->actingAs($this->admin())
            ->get('/admin/teachers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Teachers/Index')
                ->has('teachers.data', 1)
                ->where('teachers.data.0.name', 'Ahmed Khan')
                ->where('teachers.data.0.subjects', ['Physics'])
                ->where('teachers.data.0.assignments_count', 1)
                ->where('filters.status', 'all')
                ->where('filters.search', '')
                ->has('subjects', 1)
                ->has('classes', 1));
    }

    public function test_index_filters_by_search_status_and_assignment(): void
    {
        $physicsTeacher = $this->teacher('Ahmed Khan');
        $this->teacher('Bilal Ahmad', ['is_active' => false]);

        ClassSubject::create([
            'class_id' => $this->classModel()->id,
            'subject_id' => $this->subject('Physics')->id,
            'teacher_id' => $physicsTeacher->id,
            'periods_per_week' => 7,
        ]);

        $this->actingAs($this->admin())->get('/admin/teachers?search=ahmed')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('teachers.data', 1)
                ->where('teachers.data.0.name', 'Ahmed Khan')
                ->where('filters.search', 'ahmed'));

        $this->actingAs($this->admin())->get('/admin/teachers?status=inactive')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('teachers.data', 1)
                ->where('teachers.data.0.name', 'Bilal Ahmad')
                ->where('filters.status', 'inactive'));

        // A subject filter keeps only teachers who actually hold that subject.
        $physicsSubjectId = $physicsTeacher->teacherAssignments()->value('subject_id');

        $this->actingAs($this->admin())
            ->get('/admin/teachers?subject_id='.$physicsSubjectId)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('teachers.data', 1)
                ->where('teachers.data.0.name', 'Ahmed Khan'));

        // A class filter behaves the same way.
        $this->actingAs($this->admin())
            ->get('/admin/teachers?class_id='.$physicsTeacher->teacherAssignments()->value('class_id'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('teachers.data', 1)
                ->where('teachers.data.0.name', 'Ahmed Khan'));

        // An unknown status falls back to "all" rather than filtering.
        $this->actingAs($this->admin())->get('/admin/teachers?status=bogus')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('teachers.data', 2)
                ->where('filters.status', 'all'));
    }

    public function test_store_creates_an_active_teacher_with_a_hashed_password(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/teachers', $this->validPayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.teachers.index'));

        $teacher = User::where('email', 'test.teacher@college.test')->first();

        $this->assertNotNull($teacher);
        $this->assertTrue($teacher->isTeacher());
        $this->assertTrue($teacher->is_active);
        $this->assertSame('0300-1234567', $teacher->phone);
        $this->assertTrue(Hash::check('TestPass123', $teacher->password));
    }

    public function test_store_cannot_be_used_to_create_another_role(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/teachers', $this->validPayload(['role' => 'admin']))
            ->assertSessionHasNoErrors();

        $this->assertTrue(
            User::where('email', 'test.teacher@college.test')->first()->isTeacher()
        );
    }

    public function test_store_rejects_duplicate_email_and_phone(): void
    {
        $this->teacher('Ahmed Khan', [
            'email' => 'taken@college.test',
            'phone' => '0300-9999999',
        ]);

        $this->actingAs($this->admin())
            ->from('/admin/teachers/create')
            ->post('/admin/teachers', $this->validPayload([
                'email' => 'taken@college.test',
                'phone' => '0300-9999999',
            ]))
            ->assertSessionHasErrors(['email', 'phone']);

        $this->assertDatabaseMissing('users', [
            'email' => 'test.teacher@college.test',
        ]);
    }

    public function test_show_lists_the_assignments_without_the_password(): void
    {
        $teacher = $this->teacher();
        $subject = $this->subject('Physics');
        $class = $this->classModel();

        ClassSubject::create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'periods_per_week' => 7,
        ]);

        $response = $this->actingAs($this->admin())
            ->get("/admin/teachers/{$teacher->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Teachers/Show')
                ->where('teacher.name', 'Ahmed Khan')
                ->has('assignments', 1)
                ->where('assignments.0.class_display_name', '11th Pre-Medical A')
                ->where('assignments.0.subject_name', 'Physics')
                ->where('assignments.0.periods_per_week', 7)
                ->where('teacher.created_at', $teacher->created_at->toDateString()));

        $this->assertStringNotContainsString(
            $teacher->password,
            $response->getContent()
        );
    }

    public function test_edit_page_never_sends_the_password(): void
    {
        $teacher = $this->teacher();

        $response = $this->actingAs($this->admin())
            ->get("/admin/teachers/{$teacher->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Teachers/Edit')
                ->where('teacher.email', $teacher->email)
                ->missing('teacher.password'));

        $this->assertStringNotContainsString(
            $teacher->password,
            $response->getContent()
        );
    }

    public function test_update_changes_details_and_keeps_the_password_when_blank(): void
    {
        $teacher = $this->teacher();
        $originalHash = $teacher->password;

        $this->actingAs($this->admin())
            ->put("/admin/teachers/{$teacher->id}", [
                'name' => 'Ahmed KhanUpdated',
                'email' => $teacher->email,
                'phone' => '0301-7654321',
                'password' => '',
                'password_confirmation' => '',
                'is_active' => false,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.teachers.index'));

        $teacher->refresh();

        $this->assertSame('Ahmed KhanUpdated', $teacher->name);
        $this->assertSame('0301-7654321', $teacher->phone);
        $this->assertFalse($teacher->is_active);
        $this->assertSame($originalHash, $teacher->password);
    }

    public function test_update_sets_a_new_password_when_provided(): void
    {
        $teacher = $this->teacher();

        $this->actingAs($this->admin())
            ->put("/admin/teachers/{$teacher->id}", [
                'name' => $teacher->name,
                'email' => $teacher->email,
                'phone' => null,
                'password' => 'BrandNew123',
                'password_confirmation' => 'BrandNew123',
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('BrandNew123', $teacher->fresh()->password));
    }

    public function test_destroy_is_refused_while_the_teacher_has_assignments(): void
    {
        $teacher = $this->teacher();

        ClassSubject::create([
            'class_id' => $this->classModel()->id,
            'subject_id' => $this->subject()->id,
            'teacher_id' => $teacher->id,
            'periods_per_week' => 7,
        ]);

        $this->actingAs($this->admin())
            ->from('/admin/teachers')
            ->delete("/admin/teachers/{$teacher->id}")
            ->assertRedirect('/admin/teachers')
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted('users', ['id' => $teacher->id]);
    }

    public function test_destroy_soft_deletes_a_teacher_without_assignments(): void
    {
        $teacher = $this->teacher();

        $this->actingAs($this->admin())
            ->delete("/admin/teachers/{$teacher->id}")
            ->assertRedirect(route('admin.teachers.index'));

        $this->assertSoftDeleted('users', ['id' => $teacher->id]);
    }

    public function test_non_teacher_accounts_are_not_reachable_in_this_module(): void
    {
        $admin = $this->admin();
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($admin)->get("/admin/teachers/{$admin->id}")->assertNotFound();
        $this->actingAs($admin)->get("/admin/teachers/{$admin->id}/edit")->assertNotFound();
        $this->actingAs($admin)->put("/admin/teachers/{$admin->id}", [
            'name' => 'Hijacked',
            'email' => $admin->email,
            'is_active' => true,
        ])->assertNotFound();

        $this->actingAs($admin)->delete("/admin/teachers/{$student->id}")->assertNotFound();
        $this->assertNotSoftDeleted('users', ['id' => $student->id]);
    }

    public function test_non_admins_are_blocked(): void
    {
        $teacher = $this->teacher();

        $this->actingAs($teacher)->get('/admin/teachers')->assertForbidden();
        $this->actingAs($teacher)->get('/admin/teachers/create')->assertForbidden();
        $this->actingAs($teacher)
            ->post('/admin/teachers', $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseMissing('users', [
            'email' => 'test.teacher@college.test',
        ]);
    }
}
