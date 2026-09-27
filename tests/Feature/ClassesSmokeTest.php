<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\ClassModel;
use App\Models\Stream;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClassesSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function academicSession(string $name = '2026-2027'): AcademicSession
    {
        return AcademicSession::create([
            'name' => $name,
            'start_date' => '2026-08-01',
            'end_date' => '2027-05-31',
            'is_active' => true,
        ]);
    }

    private function stream(): Stream
    {
        return Stream::create([
            'name' => 'Pre-Medical',
            'code' => 'PM',
            'is_active' => true,
        ]);
    }

    public function test_index_lists_classes_with_display_name(): void
    {
        $class = ClassModel::create([
            'academic_session_id' => $this->academicSession()->id,
            'stream_id' => $this->stream()->id,
            'grade_level' => 11,
            'section' => 'A',
            'capacity' => 50,
            'room' => 'R-101',
            'is_active' => true,
        ]);

        $this->assertSame('11th Pre-Medical A', $class->displayName());

        $this->actingAs($this->admin())
            ->get('/admin/classes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Classes/Index')
                ->has('classes', 1)
                ->has('sessions', 1)
                ->has('streams', 1)
                ->where('classes.0.display_name', '11th Pre-Medical A')
                ->where('classes.0.academic_session', '2026-2027'));
    }

    public function test_create_and_edit_pages_render(): void
    {
        $class = ClassModel::create([
            'academic_session_id' => $this->academicSession()->id,
            'stream_id' => $this->stream()->id,
            'grade_level' => 12,
            'section' => 'B',
            'capacity' => 40,
            'room' => 'R-201',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin())
            ->get('/admin/classes/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Classes/Create')
                ->has('sessions', 1)
                ->has('streams', 1));

        $this->actingAs($this->admin())
            ->get("/admin/classes/{$class->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Classes/Edit')
                ->where('class.section', 'B')
                ->where('class.capacity', 40));
    }

    public function test_store_creates_class_and_uppercases_section(): void
    {
        $session = $this->academicSession();
        $stream = $this->stream();

        $this->actingAs($this->admin())
            ->post('/admin/classes', [
                'academic_session_id' => $session->id,
                'stream_id' => $stream->id,
                'grade_level' => 11,
                'section' => 'a',
                'capacity' => 50,
                'room' => '  R-101  ',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.classes.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('classes', [
            'academic_session_id' => $session->id,
            'stream_id' => $stream->id,
            'grade_level' => 11,
            'section' => 'A',
            'capacity' => 50,
            'room' => 'R-101',
        ]);
    }

    public function test_store_rejects_duplicate_section_in_same_session_grade_stream(): void
    {
        $session = $this->academicSession();
        $stream = $this->stream();

        ClassModel::create([
            'academic_session_id' => $session->id,
            'stream_id' => $stream->id,
            'grade_level' => 11,
            'section' => 'A',
            'capacity' => 50,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin())
            ->from('/admin/classes/create')
            ->post('/admin/classes', [
                'academic_session_id' => $session->id,
                'stream_id' => $stream->id,
                'grade_level' => 11,
                'section' => 'A',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('section');

        // The same section letter in another grade level is a different class.
        $this->actingAs($this->admin())
            ->post('/admin/classes', [
                'academic_session_id' => $session->id,
                'stream_id' => $stream->id,
                'grade_level' => 12,
                'section' => 'A',
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.classes.index'));
    }

    public function test_store_rejects_invalid_grade_level(): void
    {
        $this->actingAs($this->admin())
            ->from('/admin/classes/create')
            ->post('/admin/classes', [
                'academic_session_id' => $this->academicSession()->id,
                'stream_id' => $this->stream()->id,
                'grade_level' => 13,
                'section' => 'A',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('grade_level');

        $this->assertDatabaseCount('classes', 0);
    }

    public function test_update_and_soft_delete_frees_the_section(): void
    {
        $session = $this->academicSession();
        $stream = $this->stream();

        $class = ClassModel::create([
            'academic_session_id' => $session->id,
            'stream_id' => $stream->id,
            'grade_level' => 11,
            'section' => 'A',
            'capacity' => 50,
            'room' => 'R-101',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin())
            ->put("/admin/classes/{$class->id}", [
                'academic_session_id' => $session->id,
                'stream_id' => $stream->id,
                'grade_level' => 11,
                'section' => 'A',
                'capacity' => 60,
                'room' => 'R-107',
                'is_active' => false,
            ])
            ->assertRedirect(route('admin.classes.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('classes', [
            'id' => $class->id,
            'capacity' => 60,
            'room' => 'R-107',
            'is_active' => false,
        ]);

        $this->actingAs($this->admin())
            ->delete("/admin/classes/{$class->id}")
            ->assertRedirect(route('admin.classes.index'));

        $this->assertSoftDeleted('classes', ['id' => $class->id]);

        // The section combination is free again because the unique index is
        // partial on deleted_at.
        $this->actingAs($this->admin())
            ->post('/admin/classes', [
                'academic_session_id' => $session->id,
                'stream_id' => $stream->id,
                'grade_level' => 11,
                'section' => 'A',
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.classes.index'));
    }

    public function test_non_admins_are_blocked(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($teacher)->get('/admin/classes')->assertForbidden();
        $this->actingAs($teacher)->post('/admin/classes', [
            'academic_session_id' => $this->academicSession()->id,
            'stream_id' => $this->stream()->id,
            'grade_level' => 11,
            'section' => 'A',
            'is_active' => true,
        ])->assertForbidden();
    }
}
