<?php

namespace Tests\Feature;

use App\Models\StudentBatch;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StudentsTest extends TestCase
{
    use RefreshDatabase;

    private function batch(string $name = '2026-2028'): StudentBatch
    {
        // firstOrNew rather than create: several tests ask for the same
        // cohort more than once, and the name has a unique index on it.
        return StudentBatch::firstOrCreate(
            ['name' => $name],
            [
                'start_grade' => 11,
                'expected_graduation_year' => (int) substr($name, -4),
                'is_active' => true,
            ],
        );
    }

    /**
     * A batch created inside a test still needs an active academic session
     * before StudentBatch::current_grade will resolve one.
     */
    private function withActiveSession(): void
    {
        $this->createActiveSession();
    }

    /**
     * A student account with a profile, so the show/edit/update pages have
     * something to bind to.
     *
     * @param  array<string, mixed>  $profileAttributes
     */
    private function student(
        array $profileAttributes = [],
        array $userAttributes = [],
        ?StudentBatch $batch = null
    ): StudentProfile {
        $batch ??= $this->batch();
        $user = $this->createStudent($userAttributes);

        return StudentProfile::create([
            'user_id' => $user->id,
            'batch_id' => $batch->id,
            'roll_number' => StudentProfile::nextRollNumber($batch->id),
            ...$profileAttributes,
        ]);
    }

    /**
     * A unique-suffix counter, so a test that posts several students does
     * not trip the unique indexes on email, phone and CNIC by reusing the
     * same values. Each call is meant to create a *new* student.
     */
    private int $sequence = 0;

    /**
     * The email the most recent validPayload() call used, so a test can
     * assert against the student it just created without repeating the
     * string.
     */
    private string $lastEmail = '';

    private function email(): string
    {
        return $this->lastEmail;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        $n = ++$this->sequence;
        $this->lastEmail = "test.student{$n}@college.test";

        return [
            'name' => 'Test Student',
            'email' => "test.student{$n}@college.test",
            'phone' => sprintf('0300-%07d', $n),
            'password' => 'TestPass123',
            'password_confirmation' => 'TestPass123',
            'is_active' => true,
            'batch_id' => $this->batch()->id,
            'roll_number' => '',
            'father_name' => 'Test Father',
            'cnic_bform' => sprintf('35202-%07d-1', $n),
            'date_of_birth' => '2009-04-12',
            'gender' => 'male',
            'guardian_phone' => '0301-1234567',
            'address' => 'House 12, Mohalla Islampura, Chiniot',
            'admission_date' => '2026-08-01',
            'previous_school' => 'Government High School Chiniot',
            'previous_marks_obtained' => 850,
            'previous_marks_total' => 1100,
            'status' => 'active',
            ...$overrides,
        ];
    }

    public function test_index_lists_students_with_their_batch_and_enrolled_class_null(): void
    {
        $this->withActiveSession();
        $student = $this->student([], ['name' => 'Ahmed Khan']);

        $this->actingAs($this->createAdmin())
            ->get('/admin/students')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Students/Index')
                ->has('students.data', 1)
                ->where('students.data.0.name', 'Ahmed Khan')
                ->where('students.data.0.batch_name', '2026-2028')
                // Always null in 3.2; the key exists so the table already
                // has the shape it needs once enrollment lands.
                ->where('students.data.0.enrolled_class', null)
                ->where('filters.search', '')
                ->where('filters.status', 'all')
                ->where('filters.gender', 'all')
                ->has('batches', 1));
    }

    public function test_index_filters_by_search_batch_gender_and_status(): void
    {
        $this->withActiveSession();
        $grade12 = $this->batch('2025-2027');

        $male = $this->student(
            ['gender' => 'male', 'cnic_bform' => '35202-1111111-1'],
            ['name' => 'Ahmed Khan'],
        );
        $female = $this->student(
            ['gender' => 'female', 'cnic_bform' => '35202-2222222-2'],
            ['name' => 'Fatima Ali'],
            $grade12,
        );

        // Search matches the name on the related user, case-insensitively.
        $this->actingAs($this->createAdmin())
            ->get('/admin/students?search=ahmed')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('students.data', 1)
                ->where('students.data.0.id', $male->id));

        // ...the CNIC, which is a column on the profile rather than the user.
        $this->actingAs($this->createAdmin())
            ->get('/admin/students?search=35202-2222222')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('students.data', 1)
                ->where('students.data.0.id', $female->id));

        // A roll number is searched as typed. Both students are 001 - one per
        // batch - so searching "001" is expected to return both; that is the
        // per-batch numbering working, not a filter that failed.
        $this->assertSame('001', $male->roll_number);
        $this->assertSame('001', $female->roll_number);

        $this->actingAs($this->createAdmin())
            ->get('/admin/students?search=001')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('students.data', 2));

        $this->actingAs($this->createAdmin())
            ->get('/admin/students?search=35202-1111111')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('students.data', 1)
                ->where('students.data.0.id', $male->id));

        // Batch, gender and status narrow the same list.
        $this->actingAs($this->createAdmin())
            ->get('/admin/students?batch_id='.$grade12->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('students.data', 1)
                ->where('filters.batch_id', $grade12->id));

        $this->actingAs($this->createAdmin())
            ->get('/admin/students?gender=female')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('students.data', 1)
                ->where('filters.gender', 'female'));

        $this->actingAs($this->createAdmin())
            ->get('/admin/students?status=graduated')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('students.data', 0)
                ->where('filters.status', 'graduated'));

        // An unknown value falls back to "all" rather than filtering on it.
        $this->actingAs($this->createAdmin())
            ->get('/admin/students?status=bogus&gender=nonsense')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('students.data', 2)
                ->where('filters.status', 'all')
                ->where('filters.gender', 'all'));
    }

    public function test_create_offers_the_batches_and_a_roll_number_preview(): void
    {
        $this->withActiveSession();
        $this->batch('2025-2027');
        $newest = $this->batch('2026-2028');

        // One student already sits on the newest batch, so the preview is
        // the number after theirs, not 001.
        $this->student([], [], $newest);

        $this->actingAs($this->createAdmin())
            ->get('/admin/students/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Students/Create')
                ->where('defaultBatchId', $newest->id)
                ->where('nextRollNumber', '002')
                ->has('batches', 2));
    }

    public function test_store_creates_the_account_and_the_profile_together(): void
    {
        $this->withActiveSession();
        $batch = $this->batch();

        $this->actingAs($this->createAdmin())
            ->post('/admin/students', $this->validPayload(['batch_id' => $batch->id]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.students.index'));

        $user = User::where('email', $this->email())->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->isStudent());
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('TestPass123', $user->password));

        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $user->id,
            'batch_id' => $batch->id,
            // Blank on the form, so the server assigned the first free number.
            'roll_number' => '001',
            'father_name' => 'Test Father',
            'gender' => 'male',
            'status' => 'active',
            'previous_marks_obtained' => 850,
            'previous_marks_total' => 1100,
        ]);
    }

    public function test_store_assigns_roll_numbers_per_batch_not_globally(): void
    {
        $this->withActiveSession();
        $grade12 = $this->batch('2025-2027');
        $grade11 = $this->batch('2026-2028');

        // A student on each batch. Both are 001, which is the point: the
        // number counts within a cohort, not across the whole college.
        $this->actingAs($this->createAdmin())
            ->post('/admin/students', $this->validPayload([
                'email' => 'first@college.test',
                'phone' => '0300-1111111',
                'batch_id' => $grade12->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->createAdmin())
            ->post('/admin/students', $this->validPayload([
                'email' => 'second@college.test',
                'phone' => '0300-2222222',
                'batch_id' => $grade11->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('student_profiles', [
            'user_id' => User::where('email', 'first@college.test')->value('id'),
            'roll_number' => '001',
        ]);
        $this->assertDatabaseHas('student_profiles', [
            'user_id' => User::where('email', 'second@college.test')->value('id'),
            'roll_number' => '001',
        ]);

        // A second student on the same batch gets the next number.
        $this->actingAs($this->createAdmin())
            ->post('/admin/students', $this->validPayload([
                'email' => 'third@college.test',
                'phone' => '0300-3333333',
                'batch_id' => $grade11->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('student_profiles', [
            'user_id' => User::where('email', 'third@college.test')->value('id'),
            'roll_number' => '002',
        ]);
    }

    public function test_store_keeps_a_typed_roll_number_and_rejects_a_duplicate_in_the_same_batch(): void
    {
        $this->withActiveSession();
        $batch = $this->batch();
        $other = $this->batch('2025-2027');

        $this->actingAs($this->createAdmin())
            ->post('/admin/students', $this->validPayload([
                'batch_id' => $batch->id,
                'roll_number' => 'A-01',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('student_profiles', ['roll_number' => 'A-01']);

        // The same number in a different batch is fine.
        $this->actingAs($this->createAdmin())
            ->post('/admin/students', $this->validPayload([
                'email' => 'other.batch@college.test',
                'phone' => '0300-4444444',
                'batch_id' => $other->id,
                'roll_number' => 'A-01',
            ]))
            ->assertSessionHasNoErrors();

        // The same number in the same batch is not.
        $this->actingAs($this->createAdmin())
            ->post('/admin/students', $this->validPayload([
                'email' => 'clash@college.test',
                'phone' => '0300-5555555',
                'batch_id' => $batch->id,
                'roll_number' => 'A-01',
            ]))
            ->assertSessionHasErrors('roll_number');

        $this->assertDatabaseMissing('users', ['email' => 'clash@college.test']);
    }

    public function test_store_rejects_a_duplicate_cnic_but_not_a_missing_one(): void
    {
        $this->withActiveSession();

        $this->actingAs($this->createAdmin())
            ->post('/admin/students', $this->validPayload([
                'cnic_bform' => '35202-1234567-1',
            ]))
            ->assertSessionHasNoErrors();

        // The same CNIC as the student created above. The email and phone
        // are left to the helper, which makes them unique per call, so the
        // CNIC is the only clash here.
        $this->actingAs($this->createAdmin())
            ->post('/admin/students', $this->validPayload([
                'cnic_bform' => '35202-1234567-1',
            ]))
            ->assertSessionHasErrors('cnic_bform');

        // A CNIC is not required, and two students with none are allowed:
        // the unique index only covers rows where it is set.
        $this->actingAs($this->createAdmin())
            ->post('/admin/students', $this->validPayload([
                'email' => 'no.cnic@college.test',
                'phone' => '0300-7777777',
            ]))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->createAdmin())
            ->post('/admin/students', $this->validPayload([
                'email' => 'also.no.cnic@college.test',
                'phone' => '0300-8888888',
                'cnic_bform' => '',
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_store_rejects_duplicate_email_and_an_invalid_marks_pair(): void
    {
        $this->withActiveSession();

        $this->actingAs($this->createAdmin())
            ->post('/admin/students', $this->validPayload())
            ->assertSessionHasNoErrors();

        // The same email again - a real clash this time, because the first
        // post went through.
        $duplicateEmail = $this->email();

        $this->actingAs($this->createAdmin())
            ->post('/admin/students', $this->validPayload([
                'email' => $duplicateEmail,
            ]))
            ->assertSessionHasErrors('email');

        // Obtained cannot exceed the total.
        $this->actingAs($this->createAdmin())
            ->post('/admin/students', $this->validPayload([
                'previous_marks_obtained' => 1200,
                'previous_marks_total' => 1100,
            ]))
            ->assertSessionHasErrors('previous_marks_total');
    }

    public function test_store_cannot_be_used_to_create_another_role(): void
    {
        $this->withActiveSession();

        $this->actingAs($this->createAdmin())
            ->post('/admin/students', $this->validPayload(['role' => 'admin']))
            ->assertSessionHasNoErrors();

        $this->assertTrue(
            User::where('email', $this->email())->first()->isStudent()
        );
    }

    public function test_show_renders_the_profile_with_enrollments_left_empty(): void
    {
        $this->withActiveSession();
        $student = $this->student(
            ['father_name' => 'Ali Senior'],
            ['name' => 'Ahmed Khan', 'phone' => '0300-1234567'],
        );

        $this->actingAs($this->createAdmin())
            ->get("/admin/students/{$student->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Students/Show')
                ->where('student.name', 'Ahmed Khan')
                ->where('student.email', $student->user->email)
                ->where('student.phone', '0300-1234567')
                ->where('student.roll_number', $student->roll_number)
                ->where('student.father_name', 'Ali Senior')
                ->where('student.batch.name', '2026-2028')
                // Enrollments arrive in 3.4.
                ->has('enrollments', 0));
    }

    public function test_update_changes_the_account_and_the_profile(): void
    {
        $this->withActiveSession();
        $student = $this->student(
            ['father_name' => 'Old Father'],
            ['name' => 'Old Name', 'phone' => '0300-1111111'],
        );

        $this->actingAs($this->createAdmin())
            ->put("/admin/students/{$student->id}", [
                'name' => 'New Name',
                'email' => $student->user->email,
                'phone' => '0300-2222222',
                'password' => '',
                'password_confirmation' => '',
                'is_active' => false,
                'batch_id' => $student->batch_id,
                'roll_number' => '042',
                'father_name' => 'New Father',
                'cnic_bform' => $student->cnic_bform,
                'date_of_birth' => $student->date_of_birth?->toDateString(),
                'gender' => 'female',
                'guardian_phone' => null,
                'address' => null,
                'admission_date' => $student->admission_date?->toDateString(),
                'previous_school' => null,
                'previous_marks_obtained' => 900,
                'previous_marks_total' => 1100,
                'status' => 'suspended',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.students.index'));

        $student->refresh();

        $this->assertSame('New Name', $student->user->name);
        $this->assertSame('0300-2222222', $student->user->phone);
        $this->assertFalse($student->user->is_active);
        $this->assertSame('042', $student->roll_number);
        $this->assertSame('New Father', $student->father_name);
        $this->assertSame('female', $student->gender);
        $this->assertSame('suspended', $student->status);
        $this->assertSame(900, $student->previous_marks_obtained);
    }

    public function test_update_keeps_the_password_when_the_field_is_blank(): void
    {
        $this->withActiveSession();
        $student = $this->student();
        $originalHash = $student->user->password;

        $this->actingAs($this->createAdmin())
            ->put("/admin/students/{$student->id}", [
                'name' => $student->user->name,
                'email' => $student->user->email,
                'phone' => null,
                'password' => '',
                'password_confirmation' => '',
                'is_active' => true,
                'batch_id' => $student->batch_id,
                'roll_number' => $student->roll_number,
                'status' => 'active',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($originalHash, $student->fresh()->user->password);
    }

    public function test_update_sets_a_new_password_when_provided(): void
    {
        $this->withActiveSession();
        $student = $this->student();

        $this->actingAs($this->createAdmin())
            ->put("/admin/students/{$student->id}", [
                'name' => $student->user->name,
                'email' => $student->user->email,
                'phone' => null,
                'password' => 'BrandNew123',
                'password_confirmation' => 'BrandNew123',
                'is_active' => true,
                'batch_id' => $student->batch_id,
                'roll_number' => $student->roll_number,
                'status' => 'active',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(
            Hash::check('BrandNew123', $student->fresh()->user->password)
        );
    }

    public function test_update_does_not_let_a_student_grab_another_accounts_email(): void
    {
        $this->withActiveSession();
        $student = $this->student();
        $other = $this->createStudent(['email' => 'someone.else@college.test']);

        $this->actingAs($this->createAdmin())
            ->put("/admin/students/{$student->id}", [
                'name' => $student->user->name,
                'email' => $other->email,
                'phone' => null,
                'password' => '',
                'password_confirmation' => '',
                'is_active' => true,
                'batch_id' => $student->batch_id,
                'roll_number' => $student->roll_number,
                'status' => 'active',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_destroy_soft_deletes_the_profile_and_the_account(): void
    {
        $this->withActiveSession();
        $student = $this->student();
        $userId = $student->user_id;

        $this->actingAs($this->createAdmin())
            ->delete("/admin/students/{$student->id}")
            ->assertRedirect(route('admin.students.index'));

        // Both rows go, so a withdrawn student leaves no live login behind.
        $this->assertSoftDeleted('student_profiles', ['id' => $student->id]);
        $this->assertSoftDeleted('users', ['id' => $userId]);
    }

    public function test_a_deleted_student_frees_their_roll_number_and_cnic(): void
    {
        $this->withActiveSession();
        $batch = $this->batch();
        $student = $this->student(['cnic_bform' => '35202-9999999-9'], [], $batch);

        $this->actingAs($this->createAdmin())
            ->delete("/admin/students/{$student->id}");

        // Both unique indexes on the profile are partial on deleted_at, so a
        // deleted student's number and CNIC are both available again.
        $this->assertSame(
            $student->roll_number,
            StudentProfile::nextRollNumber($batch->id),
        );

        $this->actingAs($this->createAdmin())
            ->post('/admin/students', $this->validPayload([
                'batch_id' => $batch->id,
                'roll_number' => $student->roll_number,
                'cnic_bform' => '35202-9999999-9',
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_non_student_profiles_are_not_reachable_in_this_module(): void
    {
        $this->withActiveSession();
        $teacher = $this->createTeacher();

        // A profile hanging off a non-student account must not be reachable,
        // so a crafted URL cannot read or edit another role's data.
        $profile = StudentProfile::create([
            'user_id' => $teacher->id,
            'batch_id' => $this->batch()->id,
            'roll_number' => '001',
        ]);

        $admin = $this->createAdmin();

        $this->actingAs($admin)->get("/admin/students/{$profile->id}")->assertNotFound();
        $this->actingAs($admin)->get("/admin/students/{$profile->id}/edit")->assertNotFound();
        $this->actingAs($admin)
            ->put("/admin/students/{$profile->id}", [
                'name' => 'Hijacked',
                'email' => $teacher->email,
                'is_active' => true,
                'batch_id' => $profile->batch_id,
            ])
            ->assertNotFound();
        $this->actingAs($admin)
            ->delete("/admin/students/{$profile->id}")
            ->assertNotFound();

        $this->assertNotSoftDeleted('student_profiles', ['id' => $profile->id]);
        $this->assertNotSoftDeleted('users', ['id' => $teacher->id]);
    }

    public function test_non_admins_are_blocked(): void
    {
        $this->withActiveSession();
        $student = $this->student();

        $this->actingAs($this->createTeacher())
            ->get('/admin/students')
            ->assertForbidden();
        $this->actingAs($this->createTeacher())
            ->get('/admin/students/create')
            ->assertForbidden();
        $this->actingAs($this->createStudent())
            ->get("/admin/students/{$student->id}")
            ->assertForbidden();

        $this->assertDatabaseCount('student_profiles', 1);
    }
}
