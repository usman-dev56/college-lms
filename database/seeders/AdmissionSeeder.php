<?php

namespace Database\Seeders;

use App\Models\Admission;
use App\Models\Stream;
use App\Models\StudentBatch;
use Illuminate\Database\Seeder;

class AdmissionSeeder extends Seeder
{
    /**
     * Given names applicants are drawn from, male and female kept apart so
     * the row and the father's name agree with each other.
     *
     * @var array<int, string>
     */
    private const MALE_NAMES = [
        'Hamza', 'Zain', 'Bilal', 'Usman', 'Faisal', 'Imran', 'Danish', 'Adnan',
    ];

    /** @var array<int, string> */
    private const FEMALE_NAMES = ['Ayesha', 'Fatima', 'Hina', 'Sadia', 'Maria'];

    /**
     * Family names, plausible for the Chiniot / Faisalabad area.
     *
     * @var array<int, string>
     */
    private const FAMILY_NAMES = [
        'Khan', 'Ali', 'Malik', 'Sheikh', 'Ahmad', 'Hussain', 'Iqbal', 'Tariq',
        'Raza', 'Abbas', 'Nawaz', 'Farooq', 'Javed', 'Aslam',
    ];

    /**
     * Fathers' names. Male throughout, which is what the column asks for.
     *
     * @var array<int, string>
     */
    private const FATHER_NAMES = [
        'Muhammad', 'Rashid', 'Nadeem', 'Shakeel', 'Ilyas', 'Zafar', 'Mansoor', 'Tanveer',
    ];

    /**
     * Mohallas and towns a Chiniot address is built from.
     *
     * @var array<int, string>
     */
    private const LOCALITIES = [
        'Mohalla Islampura', 'Mohalla Rashidpur', 'Mohalla Ghanta Ghar',
        'Mohalla Karam Nagar', 'Chiniot City', 'Lalyar', 'Satoki', 'Mianwali',
    ];

    /**
     * Schools a matric applicant in the district would have attended.
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
     * Seed the application queue.
     *
     * Fifteen applications across the two running batches, rotated over
     * every active stream so the merit list has something to rank and the
     * office has a mix to work through.
     *
     * Idempotent: rows are matched on the CNIC, which is the one thing that
     * has to be unique across the whole college and is what an applicant is
     * de-duplicated on anyway. Re-running updates the existing applications
     * rather than duplicating them, and the application number is left alone
     * for the same reason the students seeder preserves roll numbers - a
     * renumbered application is one the applicant has already been quoted and
     * would no longer match.
     *
     * Two applications are left without marks on purpose. The merit list
     * has an "awaiting marks" section for exactly this case, and seeding
     * only clean rows would mean that branch of the page is never exercised
     * against real data.
     */
    public function run(): void
    {
        $batches = StudentBatch::query()
            ->whereIn('name', ['2026-2028', '2025-2027'])
            ->orderBy('name')
            ->get()
            ->keyBy('name');

        $streams = Stream::query()
            ->active()
            ->orderBy('name')
            ->get();

        if ($batches->isEmpty() || $streams->isEmpty()) {
            $this->command?->warn('Batches or streams missing. Run the earlier seeders first.');

            return;
        }

        // 2026-2028 takes the first eight, 2025-2027 the last seven.
        $plan = [
            '2026-2028' => 8,
            '2025-2027' => 7,
        ];

        $index = 0;
        $maleCursor = 0;
        $femaleCursor = 0;
        $familyCursor = 0;

        foreach ($plan as $batchName => $count) {
            $batch = $batches->get($batchName);

            if ($batch === null) {
                continue;
            }

            for ($position = 0; $position < $count; $position++) {
                $isMale = $position % 2 === 0;

                $first = $isMale
                    ? self::MALE_NAMES[$maleCursor++ % count(self::MALE_NAMES)]
                    : self::FEMALE_NAMES[$femaleCursor++ % count(self::FEMALE_NAMES)];

                $last = self::FAMILY_NAMES[$familyCursor++ % count(self::FAMILY_NAMES)];

                $cnic = $this->cnic($index);

                $existing = Admission::withTrashed()->where('cnic_bform', $cnic)->first();

                Admission::updateOrCreate(
                    ['cnic_bform' => $cnic],
                    [
                        'application_number' => $existing?->application_number
                            ?? Admission::generateApplicationNumber((int) date('Y')),
                        'applicant_name' => "{$first} {$last}",
                        'father_name' => self::FATHER_NAMES[$index % count(self::FATHER_NAMES)],
                        'date_of_birth' => fake()->dateTimeBetween('2008-01-01', '2010-12-31')->format('Y-m-d'),
                        'phone' => $this->phone($index),
                        'guardian_phone' => $this->phone($index + 500),
                        'address' => sprintf(
                            'House %d, %s, Chiniot',
                            ($index * 13) % 250 + 1,
                            self::LOCALITIES[$index % count(self::LOCALITIES)],
                        ),
                        'previous_school' => self::SCHOOLS[$index % count(self::SCHOOLS)],

                        // Every third application has no marks, standing in
                        // for one somebody filed before they had the result.
                        'previous_marks_obtained' => $index % 3 === 0
                            ? null
                            : fake()->numberBetween(550, 1050),
                        'previous_marks_total' => 1100,
                        'stream_applied_id' => $streams[$index % $streams->count()]->id,
                        'batch_id' => $batch->id,
                        'status' => 'pending',
                        'merit_rank' => null,
                    ],
                );

                $index++;
            }
        }
    }

    /**
     * A unique 13-digit CNIC in the 35202-XXXXXXX-X format.
     */
    private function cnic(int $seed): string
    {
        return sprintf('35202-%07d-%d', ($seed * 7919) % 10000000, $seed % 10);
    }

    /**
     * A unique mobile number in the local 03XX-XXXXXXX format.
     */
    private function phone(int $seed): string
    {
        return sprintf('0%d-%07d', 30 + ($seed % 10), ($seed * 137) % 10000000);
    }
}
