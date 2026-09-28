<?php

namespace Database\Seeders;

use App\Models\StudentBatch;
use Illuminate\Database\Seeder;

class StudentBatchSeeder extends Seeder
{
    /**
     * Seed the cohorts the college runs student batches in.
     *
     * Three of them, so the list shows two active year groups side by side -
     * which is the whole point of batches being separate from sessions - plus
     * a graduated cohort, so the status filter has something to filter.
     *
     * Idempotent: rows are matched on the name, which is the cohort's
     * identity, so re-running updates the existing rows rather than
     * duplicating them.
     */
    public function run(): void
    {
        $batches = [
            [
                'name' => '2025-2027',
                'start_grade' => 11,
                'expected_graduation_year' => 2027,
                'is_active' => true,
                'notes' => 'Current Grade 12 cohort',
            ],
            [
                'name' => '2026-2028',
                'start_grade' => 11,
                'expected_graduation_year' => 2028,
                'is_active' => true,
                'notes' => 'Current Grade 11 cohort',
            ],
            [
                'name' => '2024-2026',
                'start_grade' => 11,
                'expected_graduation_year' => 2026,
                'is_active' => false,
                'notes' => 'Graduated',
            ],
        ];

        foreach ($batches as $batch) {
            StudentBatch::updateOrCreate(
                ['name' => $batch['name']],
                $batch,
            );
        }
    }
}
