<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Stream;
use App\Models\StudentBatch;
use App\Models\StudentProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The admissions workflow, public form through to an enrolled student.
 *
 * The helpers below build the smallest thing each test needs. createStream()
 * comes from TestCase; a batch helper does not exist there, and the batches
 * these tests need differ only by name, so they are made here.
 */
class AdmissionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * firstOrCreate rather than create: several tests ask for the same
     * cohort or stream twice, and both have unique indexes on the name.
     */
    private function batch(string $name = '2026-2028', bool $open = true): StudentBatch
    {
        return StudentBatch::firstOrCreate(
            ['name' => $name],
            [
                'start_grade' => 11,
                'expected_graduation_year' => (int) substr($name, -4),
                'is_active' => true,
                'admissions_open' => $open,
            ],
        );
    }

    private function activeStream(string $name = 'Pre-Medical'): Stream
    {
        return Stream::firstOrCreate(
            ['name' => $name],
            ['code' => 'PM', 'is_active' => true],
        );
    }

    /**
     * An application ready to be worked through.
     *
     * Each one gets its own CNIC and application number, because both are
     * unique across the table and a test that creates several would otherwise
     * trip the same index.
     */
    private function admission(
        array $attributes = [],
        ?StudentBatch $batch = null,
        ?Stream $stream = null,
    ): Admission {
        static $counter = 0;
        $counter++;

        $batch ??= $this->batch();
        $stream ??= $this->activeStream();

        return Admission::create([
            'application_number' => 'ADM-2026-'.str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
            'applicant_name' => 'Hamza Yousaf',
            'father_name' => 'Muhammad Yousaf',
            'cnic_bform' => sprintf('35202-%07d-1', $counter),
            'phone' => sprintf('0300-%07d', $counter),
            'stream_applied_id' => $stream->id,
            'batch_id' => $batch->id,
            'status' => 'pending',
            'previous_marks_obtained' => 850,
            'previous_marks_total' => 1100,
            ...$attributes,
        ]);
    }

    /**
     * A valid public application payload.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function publicPayload(array $overrides = []): array
    {
        static $counter = 1000;
        $counter++;

        return [
            'applicant_name' => 'Test Applicant',
            'father_name' => 'Test Father',
            'cnic_bform' => sprintf('35202-%07d-1', $counter),
            'stream_applied_id' => $this->activeStream()->id,
            'batch_id' => $this->batch()->id,
            ...$overrides,
        ];
    }

    public function test_public_form_renders_when_a_batch_is_open(): void
    {
        $this->batch();
        $this->activeStream();

        $this->get('/admissions/apply')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Admissions/Create')
                ->where('admissionsClosed', false)
                ->has('batches', 1)
                ->has('streams', 1));
    }

    public function test_public_form_shows_closed_message_when_no_batch_is_open(): void
    {
        // A batch exists but is closed, which is the realistic case: the
        // cohort is real, it just is not taking applications.
        $this->batch('2026-2028', open: false);

        $this->get('/admissions/apply')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Admissions/Create')
                ->where('admissionsClosed', true)
                ->has('batches', 0));
    }

    public function test_public_submission_creates_a_pending_application_with_sequential_number(): void
    {
        $this->batch();

        // The first application in a fresh database is 0001, so the number
        // is known before the post rather than read back after it.
        $expected = 'ADM-'.date('Y').'-0001';
        $this->assertSame($expected, Admission::generateApplicationNumber((int) date('Y')));

        $response = $this->post('/admissions/apply', $this->publicPayload());

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admissions.success', ['application_number' => $expected]));

        $this->assertDatabaseHas('admissions', [
            'application_number' => $expected,
            'applicant_name' => 'Test Applicant',
            'status' => 'pending',
        ]);
    }

    public function test_public_submission_rejects_duplicate_cnic(): void
    {
        $this->batch();
        $payload = $this->publicPayload();

        $this->post('/admissions/apply', $payload)->assertSessionHasNoErrors();

        $this->post('/admissions/apply', $payload)
            ->assertSessionHasErrors('cnic_bform');

        $this->assertDatabaseCount('admissions', 1);
    }

    public function test_public_submission_rejects_closed_batch(): void
    {
        $closed = $this->batch('2026-2028', open: false);
        $this->activeStream();

        $this->post('/admissions/apply', $this->publicPayload(['batch_id' => $closed->id]))
            ->assertSessionHasErrors('batch_id');

        $this->assertDatabaseCount('admissions', 0);
    }

    public function test_admin_index_lists_applications_and_filters_by_status(): void
    {
        $this->admission(['status' => 'pending', 'applicant_name' => 'Pending One']);
        $this->admission(['status' => 'accepted', 'applicant_name' => 'Accepted One']);

        $this->actingAs($this->createAdmin())
            ->get('/admin/admissions')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Admissions/Index')
                ->has('admissions.data', 2)
                ->where('counts.pending', 1)
                ->where('counts.accepted', 1)
                ->where('counts.rejected', 0));

        $this->actingAs($this->createAdmin())
            ->get('/admin/admissions?status=pending')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('admissions.data', 1)
                ->where('admissions.data.0.applicant_name', 'Pending One')
                ->where('filters.status', 'pending'));
    }

    public function test_admin_index_filters_by_batch_and_stream(): void
    {
        $other = $this->batch('2025-2027');
        $ics = Stream::create(['name' => 'ICS', 'code' => 'ICS', 'is_active' => true]);

        $first = $this->admission();
        $this->admission(batch: $other, stream: $ics);

        $this->actingAs($this->createAdmin())
            ->get('/admin/admissions?batch_id='.$other->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('admissions.data', 1)
                ->where('filters.batch_id', $other->id));

        $this->actingAs($this->createAdmin())
            ->get('/admin/admissions?stream_id='.$ics->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('admissions.data', 1)
                ->where('filters.stream_id', $ics->id));

        $this->assertNotNull($first);
    }

    public function test_admin_can_mark_pending_as_reviewed(): void
    {
        $admission = $this->admission();
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->patch("/admin/admissions/{$admission->id}/review")
            ->assertRedirect();

        $admission->refresh();

        $this->assertSame('reviewed', $admission->status);
        $this->assertNotNull($admission->reviewed_at);
        $this->assertSame($admin->id, $admission->reviewed_by);
    }

    public function test_admin_can_accept_reviewed_application(): void
    {
        $admission = $this->admission(['status' => 'reviewed']);

        $this->actingAs($this->createAdmin())
            ->patch("/admin/admissions/{$admission->id}/accept")
            ->assertRedirect();

        $this->assertSame('accepted', $admission->fresh()->status);
    }

    public function test_admin_can_reject_with_a_reason(): void
    {
        $admission = $this->admission();

        $this->actingAs($this->createAdmin())
            ->patch("/admin/admissions/{$admission->id}/reject", [
                'rejection_reason' => 'Below the merit threshold',
            ])
            ->assertRedirect();

        $admission->refresh();

        $this->assertSame('rejected', $admission->status);
        $this->assertSame('Below the merit threshold', $admission->rejection_reason);
    }

    public function test_invalid_status_transition_aborts_with_422(): void
    {
        // An enrolled application is past every review action. Trying to
        // review it must be refused rather than quietly resetting it.
        $admission = $this->admission(['status' => 'enrolled']);

        $this->actingAs($this->createAdmin())
            ->patch("/admin/admissions/{$admission->id}/review")
            ->assertStatus(422);

        $this->assertSame('enrolled', $admission->fresh()->status);
    }

    public function test_reject_requires_a_reason(): void
    {
        $admission = $this->admission();

        $this->actingAs($this->createAdmin())
            ->patch("/admin/admissions/{$admission->id}/reject", [])
            ->assertSessionHasErrors('rejection_reason');

        $this->assertSame('pending', $admission->fresh()->status);
    }

    public function test_merit_list_ranks_applications_by_percentage(): void
    {
        $batch = $this->batch();

        // Deliberately created out of order, so the ranking has to do real
        // work rather than inherit the insertion order.
        $lowest = $this->admission(['previous_marks_obtained' => 550], $batch);
        $highest = $this->admission(['previous_marks_obtained' => 1050], $batch);
        $middle = $this->admission(['previous_marks_obtained' => 800], $batch);

        $this->actingAs($this->createAdmin())
            ->get('/admin/admissions/merit-list?batch_id='.$batch->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Admissions/MeritList')
                ->has('applications', 3)
                ->where('applications.0.id', $highest->id)
                ->where('applications.0.merit_rank', 1)
                ->where('applications.1.id', $middle->id)
                ->where('applications.1.merit_rank', 2)
                ->where('applications.2.id', $lowest->id)
                ->where('applications.2.merit_rank', 3)
                ->where('selected_batch_id', $batch->id)
                ->has('unranked', 0));

        // The ranks are written back, so a printed merit list and the page
        // cannot disagree.
        $this->assertSame(1, $highest->fresh()->merit_rank);
        $this->assertSame(2, $middle->fresh()->merit_rank);
        $this->assertSame(3, $lowest->fresh()->merit_rank);
    }

    public function test_merit_list_excludes_applications_without_marks(): void
    {
        $batch = $this->batch();

        $this->admission(['previous_marks_obtained' => 900], $batch);
        $noMarks = $this->admission([
            'previous_marks_obtained' => null,
            'previous_marks_total' => null,
        ], $batch);

        // A zero total is as unusable as a missing one: dividing by it would
        // rank the applicant last rather than saying "unknown".
        $zeroTotal = $this->admission([
            'previous_marks_obtained' => 800,
            'previous_marks_total' => 0,
        ], $batch);

        $this->actingAs($this->createAdmin())
            ->get('/admin/admissions/merit-list?batch_id='.$batch->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('applications', 1)
                ->has('unranked', 2));

        $this->assertNull($noMarks->fresh()->merit_rank);
        $this->assertNull($zeroTotal->fresh()->merit_rank);
    }

    public function test_convert_creates_user_and_profile_and_marks_admission_enrolled(): void
    {
        $admission = $this->admission([
            'status' => 'accepted',
            'father_name' => 'Muhammad Yousaf',
            'previous_school' => 'Government High School Chiniot',
        ]);
        $batch = $admission->batch;

        $this->actingAs($this->createAdmin())
            ->patch("/admin/admissions/{$admission->id}/convert")
            ->assertSessionHasNoErrors();

        $admission->refresh();

        $this->assertSame('enrolled', $admission->status);
        $this->assertNotNull($admission->enrolled_student_profile_id);

        $profile = StudentProfile::find($admission->enrolled_student_profile_id);

        $this->assertNotNull($profile);
        $this->assertSame($batch->id, $profile->batch_id);
        $this->assertSame($admission->cnic_bform, $profile->cnic_bform);
        $this->assertSame($admission->father_name, $profile->father_name);
        $this->assertSame('Government High School Chiniot', $profile->previous_school);
        $this->assertSame('active', $profile->status);

        // The roll number comes from the batch, and this was the first
        // student on it, so it must be the first number.
        $this->assertSame('001', $profile->roll_number);

        $user = $profile->user;
        $this->assertSame($admission->applicant_name, $user->name);
        $this->assertTrue($user->isStudent());
        $this->assertTrue($user->is_active);
    }

    public function test_convert_only_allowed_from_accepted_status(): void
    {
        $pending = $this->admission(['status' => 'pending']);

        $this->actingAs($this->createAdmin())
            ->patch("/admin/admissions/{$pending->id}/convert")
            ->assertStatus(422);

        $this->assertSame('pending', $pending->fresh()->status);
        $this->assertNull($pending->fresh()->enrolled_student_profile_id);
        $this->assertDatabaseCount('student_profiles', 0);
    }

    public function test_convert_flashes_the_generated_password_once(): void
    {
        $admission = $this->admission(['status' => 'accepted']);

        $this->actingAs($this->createAdmin())
            ->patch("/admin/admissions/{$admission->id}/convert")
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', function (string $message) {
                return str_contains($message, 'Password')
                    && str_contains($message, 'shown only once');
            });

        $profile = StudentProfile::find(
            $admission->fresh()->enrolled_student_profile_id
        );

        // The flash carries the plain password, but the stored hash is not
        // it - the college only ever sees it in that one response.
        $flash = session('success');
        preg_match('/Password: (\S+)/', $flash, $matches);
        $plainPassword = $matches[1];

        $this->assertNotEmpty($plainPassword);
        $this->assertNotSame($plainPassword, $profile->user->password);
        $this->assertTrue(Hash::check($plainPassword, $profile->user->password));
    }

    public function test_non_admins_cannot_access_admin_admissions(): void
    {
        $admission = $this->admission();
        $teacher = $this->createTeacher();

        $this->actingAs($teacher)->get('/admin/admissions')->assertForbidden();
        $this->actingAs($teacher)->get("/admin/admissions/{$admission->id}")->assertForbidden();
        $this->actingAs($teacher)->get('/admin/admissions/merit-list')->assertForbidden();
        $this->actingAs($teacher)
            ->patch("/admin/admissions/{$admission->id}/convert")
            ->assertForbidden();

        $this->assertSame('pending', $admission->fresh()->status);
    }
}
