<?php

namespace Database\Seeders;

use App\Models\Stream;
use Illuminate\Database\Seeder;

class StreamSeeder extends Seeder
{
    /**
     * Seed the academic streams offered by the college.
     *
     * These are the tracks Grade 11 and Grade 12 students choose from.
     * Idempotent: updateOrCreate is keyed on the unique stream code, so
     * running this seeder twice will not create duplicates.
     */
    public function run(): void
    {
        $streams = [
            [
                'name' => 'Pre-Medical',
                'code' => 'PM',
                'description' => 'FSc Pre-Medical — Biology, Physics, Chemistry',
            ],
            [
                'name' => 'Pre-Engineering',
                'code' => 'PE',
                'description' => 'FSc Pre-Engineering — Physics, Chemistry, Math',
            ],
            [
                'name' => 'ICS',
                'code' => 'ICS',
                'description' => 'Intermediate in Computer Science',
            ],
            [
                'name' => 'Commerce',
                'code' => 'COM',
                'description' => 'ICom — Accounting, Business, Economics',
            ],
            [
                'name' => 'Humanities',
                'code' => 'HUM',
                'description' => 'FA — Arts, Languages, Social Sciences',
            ],
        ];

        foreach ($streams as $stream) {
            Stream::updateOrCreate(
                ['code' => $stream['code']],
                [
                    'name' => $stream['name'],
                    'description' => $stream['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}
