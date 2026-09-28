<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Period;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PeriodsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function academicSession(string $name = '2026-2027', bool $isActive = true): AcademicSession
    {
        return AcademicSession::create([
            'name' => $name,
            'start_date' => '2026-08-01',
            'end_date' => '2027-05-31',
            'is_active' => $isActive,
        ]);
    }

    private function period(AcademicSession $session, int $number = 1, array $attributes = []): Period
    {
        return Period::create([
            'academic_session_id' => $session->id,
            'number' => $number,
            'label' => 'Period '.$number,
            'start_time' => '08:00',
            'end_time' => '08:45',
            'is_break' => false,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(AcademicSession $session, array $overrides = []): array
    {
        return [
            'academic_session_id' => $session->id,
            'number' => 1,
            'label' => 'Period 1',
            'start_time' => '08:00',
            'end_time' => '08:45',
            'is_break' => '1',
            ...$overrides,
        ];
    }

    public function test_index_lists_periods_in_grid_order_for_the_active_session(): void
    {
        $session = $this->academicSession();
        $this->period($session, 2);
        $this->period($session, 1);
        $this->period($session, 4, ['label' => 'Break', 'is_break' => true]);

        $this->actingAs($this->admin())
            ->get('/admin/periods')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Periods/Index')
                ->where('selectedSessionId', $session->id)
                ->has('periods', 3)
                ->where('periods.0.number', 1)
                ->where('periods.0.label', 'Period 1')
                // HH:MM is what an HTML time input needs.
                ->where('periods.0.start_time', '08:00')
                ->where('periods.0.end_time', '08:45')
                ->where('periods.1.number', 2)
                ->where('periods.2.number', 4)
                ->where('periods.2.is_break', true)
                ->has('sessions', 1));
    }

    public function test_index_only_shows_the_selected_session(): void
    {
        $active = $this->academicSession();
        $other = $this->academicSession('2025-2026', false);

        $this->period($active, 1);
        $this->period($other, 1, ['label' => 'Old period']);

        $this->actingAs($this->admin())
            ->get("/admin/periods?session_id={$other->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selectedSessionId', $other->id)
                ->has('periods', 1)
                ->where('periods.0.label', 'Old period'));
    }

    public function test_store_creates_a_period(): void
    {
        $session = $this->academicSession();

        $this->actingAs($this->admin())
            ->post('/admin/periods', $this->validPayload($session))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.periods.index', [
                'session_id' => $session->id,
            ]));

        $this->assertDatabaseHas('periods', [
            'academic_session_id' => $session->id,
            'number' => 1,
            'label' => 'Period 1',
            'is_break' => true,
        ]);

        $period = Period::first();
        $this->assertSame('08:00', $period->start_time->format('H:i'));
        $this->assertSame('08:45', $period->end_time->format('H:i'));
    }

    public function test_store_rejects_a_duplicate_number_in_the_same_session(): void
    {
        $session = $this->academicSession();
        $this->period($session, 1);

        $this->actingAs($this->admin())
            ->from('/admin/periods/create')
            ->post('/admin/periods', $this->validPayload($session))
            ->assertSessionHasErrors('number');

        $this->assertDatabaseCount('periods', 1);
    }

    public function test_the_same_number_is_allowed_in_a_different_session(): void
    {
        $first = $this->academicSession();
        $second = $this->academicSession('2025-2026', false);

        $this->period($first, 1);

        $this->actingAs($this->admin())
            ->post('/admin/periods', $this->validPayload($second))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('periods', 2);
    }

    public function test_a_deleted_number_can_be_reused(): void
    {
        $session = $this->academicSession();
        $removed = $this->period($session, 1);
        $removed->delete();

        // The index is partial, so the grid row is free again.
        $this->actingAs($this->admin())
            ->post('/admin/periods', $this->validPayload($session))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Period::withTrashed()->count());
    }

    public function test_store_rejects_times_that_end_before_they_start(): void
    {
        $session = $this->academicSession();

        $this->actingAs($this->admin())
            ->from('/admin/periods/create')
            ->post('/admin/periods', $this->validPayload($session, [
                'start_time' => '12:00',
                'end_time' => '11:00',
            ]))
            ->assertSessionHasErrors('end_time');

        $this->assertDatabaseCount('periods', 0);
    }

    public function test_store_rejects_a_number_outside_the_grid_range(): void
    {
        $session = $this->academicSession();

        $this->actingAs($this->admin())
            ->from('/admin/periods/create')
            ->post('/admin/periods', $this->validPayload($session, [
                'number' => 21,
            ]))
            ->assertSessionHasErrors('number');

        $this->actingAs($this->admin())
            ->from('/admin/periods/create')
            ->post('/admin/periods', $this->validPayload($session, [
                'number' => 0,
            ]))
            ->assertSessionHasErrors('number');
    }

    public function test_store_rejects_a_malformed_time(): void
    {
        $session = $this->academicSession();

        $this->actingAs($this->admin())
            ->from('/admin/periods/create')
            ->post('/admin/periods', $this->validPayload($session, [
                'start_time' => '8am',
            ]))
            ->assertSessionHasErrors('start_time');
    }

    public function test_edit_page_prefills_the_form_with_hhmm_times(): void
    {
        $session = $this->academicSession();
        $period = $this->period($session, 3, [
            'label' => 'Period 3',
            'start_time' => '09:30',
            'end_time' => '10:15',
        ]);

        $this->actingAs($this->admin())
            ->get("/admin/periods/{$period->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Periods/Edit')
                ->where('period.label', 'Period 3')
                ->where('period.number', 3)
                ->where('period.start_time', '09:30')
                ->where('period.end_time', '10:15')
                ->where('period.is_break', false)
                ->where('period.academic_session_id', $session->id));
    }

    public function test_update_changes_a_period(): void
    {
        $session = $this->academicSession();
        $period = $this->period($session, 3);

        $this->actingAs($this->admin())
            ->put("/admin/periods/{$period->id}", $this->validPayload($session, [
                'number' => 3,
                'label' => 'Period 3',
                'start_time' => '09:30',
                'end_time' => '10:15',
                'is_break' => '1',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.periods.index', [
                'session_id' => $session->id,
            ]));

        $period->refresh();

        $this->assertSame('Period 3', $period->label);
        $this->assertTrue($period->is_break);
        $this->assertSame('09:30', $period->start_time->format('H:i'));
        $this->assertSame('10:15', $period->end_time->format('H:i'));
    }

    public function test_update_allows_a_period_to_keep_its_own_number(): void
    {
        $session = $this->academicSession();
        $period = $this->period($session, 3);

        // Saving without changing the number must not collide with itself.
        $this->actingAs($this->admin())
            ->put("/admin/periods/{$period->id}", $this->validPayload($session, [
                'number' => 3,
                'label' => 'Renamed',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $period->fresh()->label);
    }

    public function test_destroy_soft_deletes_a_period(): void
    {
        $session = $this->academicSession();
        $period = $this->period($session, 5);

        $this->actingAs($this->admin())
            ->delete("/admin/periods/{$period->id}")
            ->assertRedirect(route('admin.periods.index', [
                'session_id' => $session->id,
            ]));

        $this->assertSoftDeleted('periods', ['id' => $period->id]);
    }

    public function test_non_admins_are_blocked(): void
    {
        $session = $this->academicSession();
        $teacher = User::factory()->create(['role' => 'teacher']);
        $period = $this->period($session, 1);

        $this->actingAs($teacher)->get('/admin/periods')->assertForbidden();
        $this->actingAs($teacher)->get('/admin/periods/create')->assertForbidden();
        $this->actingAs($teacher)
            ->post('/admin/periods', $this->validPayload($session))
            ->assertForbidden();
        $this->actingAs($teacher)
            ->delete("/admin/periods/{$period->id}")
            ->assertForbidden();

        $this->assertNotSoftDeleted('periods', ['id' => $period->id]);
    }
}
