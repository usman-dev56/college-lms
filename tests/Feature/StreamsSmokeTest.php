<?php

namespace Tests\Feature;

use App\Models\Stream;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StreamsSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_index_page_lists_streams(): void
    {
        Stream::create([
            'name' => 'Pre-Medical',
            'code' => 'PM',
            'description' => 'FSc Pre-Medical',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin())
            ->get('/admin/streams')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Streams/Index')
                ->has('streams', 1)
                ->where('streams.0.code', 'PM'));
    }

    public function test_create_and_edit_pages_render(): void
    {
        $stream = Stream::create(['name' => 'ICS', 'code' => 'ICS', 'is_active' => true]);

        $this->actingAs($this->admin())
            ->get('/admin/streams/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Streams/Create'));

        $this->actingAs($this->admin())
            ->get("/admin/streams/{$stream->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Streams/Edit')
                ->where('stream.code', 'ICS'));
    }

    public function test_store_uppercases_code_and_redirects(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/streams', [
                'name' => 'Commerce',
                'code' => 'com',
                'description' => 'ICom',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.streams.index'));

        $this->assertDatabaseHas('streams', ['name' => 'Commerce', 'code' => 'COM']);
    }

    public function test_store_rejects_duplicate_name_and_code(): void
    {
        Stream::create(['name' => 'Commerce', 'code' => 'COM', 'is_active' => true]);

        $this->actingAs($this->admin())
            ->from('/admin/streams/create')
            ->post('/admin/streams', [
                'name' => 'Commerce',
                'code' => 'COM',
                'is_active' => true,
            ])
            ->assertSessionHasErrors(['name', 'code']);
    }

    public function test_update_and_soft_delete(): void
    {
        $stream = Stream::create(['name' => 'Humanities', 'code' => 'HUM', 'is_active' => true]);

        $this->actingAs($this->admin())
            ->put("/admin/streams/{$stream->id}", [
                'name' => 'Humanities (FA)',
                'code' => 'HUM',
                'description' => 'FA',
                'is_active' => false,
            ])
            ->assertRedirect(route('admin.streams.index'));

        $this->assertDatabaseHas('streams', [
            'id' => $stream->id,
            'name' => 'Humanities (FA)',
        ]);

        $this->actingAs($this->admin())
            ->delete("/admin/streams/{$stream->id}")
            ->assertRedirect(route('admin.streams.index'));

        $this->assertSoftDeleted('streams', ['id' => $stream->id]);
    }

    public function test_soft_deleted_code_can_be_reused(): void
    {
        Stream::create(['name' => 'Pre-Engineering', 'code' => 'PE', 'is_active' => true])->delete();

        $this->actingAs($this->admin())
            ->post('/admin/streams', [
                'name' => 'Pre-Engineering',
                'code' => 'PE',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.streams.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Stream::withTrashed()->where('code', 'PE')->count());
    }

    public function test_non_admins_are_blocked(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($teacher)->get('/admin/streams')->assertForbidden();
    }
}
