<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\StudentBatch;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    /**
     * The first names students are drawn from, split by gender.
     *
     * Pakistani given names rather than the faker defaults, because a seeded
     * register that reads "John Smith" makes it obvious nobody looked at it.
     * Split by gender so the name and the gender column agree.
     *
     * @var array<int, string>
     */
    private const MALE_NAMES = [
        'Ahmed', 'Bilal', 'Usman', 'Hamza', 'Zain', 'Faisal', 'Imran', 'Kashif',
        'Danish', 'Adnan', 'Junaid', 'Tariq', 'Shahid', 'Nadeem', 'Rizwan', 'Salman',
    ];

    /** @var array<int, string> */
    private const FEMALE_NAMES = [
        'Fatima', 'Ayesha', 'Zara', 'Sana', 'Hina', 'Nida', 'Sadia', 'Rabia',
        'Maria', 'Komal', 'Amna', 'Iqra', 'Shazia', 'Bushra', 'Uzma', 'Nasreen',
    ];

    /**
     * Family names, all plausible for the Chiniot / Faisalabad area.
     *
     * @var array<int, string>
     */
    private const FAMILY_NAMES = [
        'Khan', 'Ali', 'Malik', 'Sheikh', 'Ahmad', 'Hussain', 'Iqbal', 'Tariq',
        'Raza', 'Abbas', 'Nawaz', 'Yasin', 'Farooq', 'Sattar', 'Javed', 'Aslam',
    ];

    /**
     * Male guardian names, used for father_name.
     *
     * @var array<int, string>
     */
    private const FATHER_NAMES = [
        'Muhammad', 'Rashid', 'Nadeem', 'Shakeel', 'Ilyas', 'Zafar', 'Mansoor', 'Tanveer',
    ];

    /**
     * The mohallas and towns a Chiniot address is built from.
     *
     * @var array<int, string>
     */
    private const LOCALITIES = [
        'Mohalla Islampura', 'Mohalla Rashidpur', 'Mohalla Ghanta Ghar',
        'Mohalla Bashahr', 'Mohalla Karam Nagar', 'Chiniot City',
        'Lalyar', 'Satoki', 'Mianwali', 'Nawanshah', 'Jhang',
    ];

    /**
     * Schools a matric student in the district would have attended.
     *
     * @var array<int, string>
     */
    private const SCHOOLS = [
        'Government High School Chiniot',
        'Boys Middle School Lalyar',
        'Girls High School Chiniot',
        'Ideal Model School Mianwali',
        'Al-Noor Public School Nawanshah',
        'Sirajia Islamia High School Jhang',
        'Government Girls High School Satoki',
        'Cambridge Model School Chiniot',
    ];

    /**
     * How many students each running batch gets: three per stream across the
     * college's five streams, so the class lists in 3.4 have a realistic
     * shape to draw from.
     */
    private const PER_BATCH = 15;

    /**
     * Seed the student roll.
     *
     * Thirty students across the two running batches. Enrollments are
     * deliberately not created: they arrive in sub-stage 3.4, and putting
     * them in now would mean rewriting this seeder later.
     *
     * Idempotent: rows are matched on the email, which is the student's
     * identity as far as the college office is concerned, so re-running
     * updates the existing students rather than duplicating them. Roll
     * numbers are preserved across re-runs for the same reason - a renumbered
     * register would be wrong, and a number already in use is not something
     * to hand out a second time.
     */
    public function run(): void
    {
        $batches = StudentBatch::query()
            ->whereIn('name', ['2026-2028', '2025-2027'])
            ->get()
            ->keyBy('name');

        if ($batches->isEmpty()) {
            $this->command?->warn('No student batches found. Run StudentBatchSeeder first.');

            return;
        }

        $streamNames = ['Pre-Medical', 'Pre-Engineering', 'ICS', 'Commerce', 'Humanities'];

        // The academic year opens in August and a batch is named for the span
        // it covers, so the intake date is the August after its first year.
        $cohorts = [
            '2026-2028' => ['admission_date' => '2026-08-01', 'streams' => $streamNames],
            '2025-2027' => ['admission_date' => '2025-08-01', 'streams' => $streamNames],
        ];

        $counter = 1;

        foreach ($cohorts as $batchName => $cohort) {
            $batch = $batches->get($batchName);

            if ($batch === null) {
                continue;
            }

            $this->seedBatch($batch, $cohort, $counter);
        }
    }

    /**
     * Seed one batch's students.
     *
     * The stream list travels with the cohort but is not written anywhere: a
     * student's stream is a property of the class they are enrolled in, not
     * of the batch, and that is what sub-stage 3.4 records. It is named here
     * so the shape of the data - three per stream - is visible in the code
     * rather than implied by a magic number.
     *
     * @param  array{admission_date:string, streams:array<int, string>}  $cohort
     */
    private function seedBatch(StudentBatch $batch, array $cohort, int &$counter): void
    {
        $maleIndex = 0;
        $femaleIndex = 0;
        $familyIndex = 0;

        for ($position = 0; $position < self::PER_BATCH; $position++) {
            // Alternating genders, so each batch comes out evenly split
            // without walking two separate cursors.
            $isMale = $position % 2 === 0;

            $first = $isMale
                ? self::MALE_NAMES[$maleIndex++ % count(self::MALE_NAMES)]
                : self::FEMALE_NAMES[$femaleIndex++ % count(self::FEMALE_NAMES)];

            $last = self::FAMILY_NAMES[$familyIndex++ % count(self::FAMILY_NAMES)];

            $email = sprintf(
                '%s.%s.%d@college.test',
                mb_strtolower($first),
                mb_strtolower($last),
                $counter,
            );

            $user = User::withTrashed()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => "{$first} {$last}",
                    // A seeded password, deliberately weak: these are demo
                    // rows for local development and every one of them shares
                    // it, so it must never reach a real deployment.
                    'password' => 'student123',
                    'role' => UserRole::Student,
                    'phone' => $this->phone($counter),
                    'is_active' => true,
                ],
            );

            $existing = StudentProfile::withTrashed()
                ->where('user_id', $user->id)
                ->first();

            StudentProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'batch_id' => $batch->id,
                    // A student who already has a number keeps it. Asking for
                    // nextRollNumber() again on a re-run would hand a live
                    // student a second number and collide with the partial
                    // unique index on the way.
                    'roll_number' => $existing?->roll_number
                        ?? StudentProfile::nextRollNumber($batch->id),
                    'cnic_bform' => $this->cnic($counter),
                    'date_of_birth' => fake()
                        ->dateTimeBetween('2008-01-01', '2010-12-31')
                        ->format('Y-m-d'),
                    'gender' => $isMale ? 'male' : 'female',
                    'father_name' => self::FATHER_NAMES[$counter % count(self::FATHER_NAMES)],
                    'guardian_phone' => $this->phone($counter + 500),
                    'address' => $this->address($counter),
                    'admission_date' => $cohort['admission_date'],
                    'previous_school' => self::SCHOOLS[$counter % count(self::SCHOOLS)],
                    'previous_marks_obtained' => fake()->numberBetween(600, 950),
                    // 1100 is the standard Matric total in Punjab, so it is
                    // the same for every student in the cohort.
                    'previous_marks_total' => 1100,
                    'status' => 'active',
                ],
            );

            $counter++;
        }
    }

    /**
     * A unique mobile number in the local format, 03XX-XXXXXXX.
     *
     * The counter is spread across the operator prefixes rather than used
     * directly, because 0300-0000001 reads like a placeholder. The caller
     * passes an offset for guardian numbers, so a student's own number and
     * their guardian's never collide.
     */
    private function phone(int $seed): string
    {
        return sprintf('0%d-%07d', 30 + ($seed % 10), ($seed * 137) % 10000000);
    }

    /**
     * A unique 13-digit CNIC in the 35202-XXXXXXX-X format.
     */
    private function cnic(int $seed): string
    {
        return sprintf('35202-%07d-%d', ($seed * 7919) % 10000000, $seed % 10);
    }

    /**
     * A street address in the district.
     */
    private function address(int $seed): string
    {
        return sprintf(
            'House %d, %s, Chiniot',
            ($seed * 13) % 250 + 1,
            self::LOCALITIES[$seed % count(self::LOCALITIES)],
        );
    }
}
