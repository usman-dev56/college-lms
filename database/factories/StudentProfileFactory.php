<?php

namespace Database\Factories;

use App\Models\StudentBatch;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentProfile>
 */
class StudentProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * A profile cannot exist without an account, a batch and a roll number,
     * so all three are built here rather than left to the test. The batch and
     * roll number are closures so they follow whatever batch the test uses -
     * pass a batch_id and the roll number is derived from that batch, which is
     * the same rule the seeder and the controller follow.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student()->create()->id,

            // Resolved before roll_number below, because the closure reads
            // $attributes['batch_id'] and gets the resolved value. A test that
            // supplies its own batch_id replaces this entry outright, so no
            // throwaway batch is created. Student batches have no factory of
            // their own, hence the inline create.
            'batch_id' => function (array $attributes) {
                if (isset($attributes['batch_id'])) {
                    return $attributes['batch_id'];
                }

                return StudentBatch::create([
                    'name' => sprintf(
                        '20%02d-20%02d',
                        fake()->numberBetween(26, 99),
                        fake()->numberBetween(27, 99),
                    ),
                    'start_grade' => 11,
                    'expected_graduation_year' => fake()->numberBetween(2027, 2099),
                    'is_active' => true,
                ])->id;
            },

            'roll_number' => fn (array $attributes) => StudentProfile::nextRollNumber(
                $attributes['batch_id'],
            ),

            'board_registration_number' => null,
            'cnic_bform' => $this->faker->unique()->numerify('35202-#######-#'),
            'date_of_birth' => $this->faker->dateTimeBetween('2008-01-01', '2010-12-31'),
            'gender' => $this->faker->randomElement(['male', 'female']),
            'father_name' => $this->faker->name(),
            'guardian_phone' => $this->faker->unique()->numerify('03##-#######'),
            'address' => $this->faker->address(),
            'photo_path' => null,
            'admission_date' => '2026-08-01',
            'previous_school' => $this->faker->company().' High School',
            'previous_marks_obtained' => $this->faker->numberBetween(600, 950),
            'previous_marks_total' => 1100,
            'status' => 'active',
        ];
    }

    /**
     * A student who has left the college but whose record is kept.
     */
    public function graduated(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'graduated']);
    }

    /**
     * A student who left before finishing the course.
     */
    public function withdrawn(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'withdrawn']);
    }

    /**
     * A student barred from attending, for example after a discipline issue.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'suspended']);
    }
}
