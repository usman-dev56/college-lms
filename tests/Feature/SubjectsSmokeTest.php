<?php

namespace Tests\Feature;

use App\Models\Stream;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SubjectsSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function stream(string $code = 'PM'): Stream
    {
        return Stream::create([
            'name' => 'Pre-Medical',
            'code' => $code,
            'is_active' => true,
        ]);
    }

    public function test_index_lists_compulsory_subjects_before_electives(): void
    {
        $stream = $this->stream();

        Subject::create([
            'name' => 'English',
            'code' => 'ENG',
            'grade_level' => 11,
            'has_practical' => false,
            'is_active' => true,
        ]);

        Subject::create([
            'name' => 'Biology',
            'code' => 'BIO',
            'grade_level' => 11,
            'stream_id' => $stream->id,
            'has_practical' => true,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin())
            ->get('/admin/subjects')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Subjects/Index')
                ->has('subjects', 2)
                ->has('streams', 1)
                ->where('subjects.0.name', 'English')
                ->where('subjects.0.stream', null)
                ->where('subjects.1.name', 'Biology')
                ->where('subjects.1.stream', 'Pre-Medical'));
    }

    public function test_create_and_edit_pages_render(): void
    {
        $stream = $this->stream();

        $subject = Subject::create([
            'name' => 'Physics',
            'code' => 'PHY',
            'grade_level' => 11,
            'stream_id' => $stream->id,
            'has_practical' => true,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin())
            ->get('/admin/subjects/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Subjects/Create')
                ->has('streams', 1));

        $this->actingAs($this->admin())
            ->get("/admin/subjects/{$subject->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Subjects/Edit')
                ->where('subject.name', 'Physics')
                ->where('subject.has_practical', true));
    }

    public function test_store_creates_compulsory_and_elective_subjects(): void
    {
        $stream = $this->stream();

        $this->actingAs($this->admin())
            ->post('/admin/subjects', [
                'name' => 'English',
                'code' => 'ENG',
                'grade_level' => 11,
                'has_practical' => false,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.subjects.index'))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin())
            ->post('/admin/subjects', [
                'name' => 'Biology',
                'code' => 'BIO',
                'grade_level' => 11,
                'stream_id' => $stream->id,
                'has_practical' => true,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.subjects.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('subjects', [
            'name' => 'English',
            'grade_level' => 11,
            'stream_id' => null,
        ]);

        $biology = Subject::query()->where('name', 'Biology')->firstOrFail();

        $this->assertSame($stream->id, $biology->stream_id);
        $this->assertTrue($biology->has_practical);
        $this->assertDatabaseHas('stream_subject', [
            'stream_id' => $stream->id,
            'subject_id' => $biology->id,
        ]);
    }

    public function test_store_rejects_duplicate_name_per_grade_and_stream(): void
    {
        Subject::create([
            'name' => 'English',
            'grade_level' => 11,
            'has_practical' => false,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin())
            ->from('/admin/subjects/create')
            ->post('/admin/subjects', [
                'name' => 'English',
                'grade_level' => 11,
                'has_practical' => false,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('name');

        // The same name in the other grade level is a different subject.
        $this->actingAs($this->admin())
            ->post('/admin/subjects', [
                'name' => 'English',
                'grade_level' => 12,
                'has_practical' => false,
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.subjects.index'));
    }

    public function test_store_rejects_invalid_grade_level(): void
    {
        $this->actingAs($this->admin())
            ->from('/admin/subjects/create')
            ->post('/admin/subjects', [
                'name' => 'Physics',
                'grade_level' => 13,
                'has_practical' => true,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('grade_level');

        $this->assertDatabaseCount('subjects', 0);
    }

    public function test_update_moves_subject_between_streams_and_syncs_pivot(): void
    {
        $stream = $this->stream();

        $subject = Subject::create([
            'name' => 'Biology',
            'grade_level' => 11,
            'stream_id' => $stream->id,
            'has_practical' => true,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin())
            ->put("/admin/subjects/{$subject->id}", [
                'name' => 'Biology',
                'grade_level' => 11,
                'stream_id' => null,
                'has_practical' => true,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.subjects.index'))
            ->assertSessionHasNoErrors();

        $subject->refresh();

        $this->assertNull($subject->stream_id);
        $this->assertSame(0, $subject->streams()->count());

        $this->actingAs($this->admin())
            ->put("/admin/subjects/{$subject->id}", [
                'name' => 'Biology',
                'grade_level' => 11,
                'stream_id' => $stream->id,
                'has_practical' => true,
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $subject->refresh();

        $this->assertSame($stream->id, $subject->stream_id);
        $this->assertSame(1, $subject->streams()->count());
    }

    public function test_destroy_soft_deletes_subject_and_frees_the_name(): void
    {
        $subject = Subject::create([
            'name' => 'English',
            'grade_level' => 11,
            'has_practical' => false,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin())
            ->delete("/admin/subjects/{$subject->id}")
            ->assertRedirect(route('admin.subjects.index'));

        $this->assertSoftDeleted('subjects', ['id' => $subject->id]);

        $this->actingAs($this->admin())
            ->post('/admin/subjects', [
                'name' => 'English',
                'grade_level' => 11,
                'has_practical' => false,
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.subjects.index'));

        $this->assertSame(2, Subject::withTrashed()->where('name', 'English')->count());
    }

    public function test_non_admins_are_blocked(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($teacher)->get('/admin/subjects')->assertForbidden();
        $this->actingAs($teacher)->post('/admin/subjects', [
            'name' => 'Physics',
            'grade_level' => 11,
            'has_practical' => true,
            'is_active' => true,
        ])->assertForbidden();
    }
}
