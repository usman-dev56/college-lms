<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\ClassModel;
use App\Models\Stream;
use Illuminate\Database\Seeder;

class ClassSeeder extends Seeder
{
    /**
     * Seed the classes (sections) of the active academic session.
     *
     * Idempotent: updateOrCreate is keyed on (academic_session_id,
     * grade_level, stream_id, section), so re-running does not duplicate.
     */
    public function run(): void
    {
        $session = AcademicSession::active()->first();

        if ($session === null) {
            $this->command?->warn('Skipped classes: there is no active academic session.');

            return;
        }

        $classes = [
            // Grade 11
            ['grade_level' => 11, 'stream' => 'PM', 'section' => 'A', 'capacity' => 50, 'room' => 'R-101'],
            ['grade_level' => 11, 'stream' => 'PM', 'section' => 'B', 'capacity' => 50, 'room' => 'R-102'],
            ['grade_level' => 11, 'stream' => 'PE', 'section' => 'A', 'capacity' => 50, 'room' => 'R-103'],
            ['grade_level' => 11, 'stream' => 'ICS', 'section' => 'A', 'capacity' => 40, 'room' => 'R-104'],
            ['grade_level' => 11, 'stream' => 'COM', 'section' => 'A', 'capacity' => 50, 'room' => 'R-105'],
            ['grade_level' => 11, 'stream' => 'HUM', 'section' => 'A', 'capacity' => 50, 'room' => 'R-106'],
            // Grade 12
            ['grade_level' => 12, 'stream' => 'PM', 'section' => 'A', 'capacity' => 50, 'room' => 'R-201'],
            ['grade_level' => 12, 'stream' => 'PM', 'section' => 'B', 'capacity' => 50, 'room' => 'R-202'],
            ['grade_level' => 12, 'stream' => 'PE', 'section' => 'A', 'capacity' => 50, 'room' => 'R-203'],
            ['grade_level' => 12, 'stream' => 'ICS', 'section' => 'A', 'capacity' => 40, 'room' => 'R-204'],
            ['grade_level' => 12, 'stream' => 'COM', 'section' => 'A', 'capacity' => 50, 'room' => 'R-205'],
            ['grade_level' => 12, 'stream' => 'HUM', 'section' => 'A', 'capacity' => 50, 'room' => 'R-206'],
        ];

        foreach ($classes as $class) {
            $stream = Stream::query()->where('code', $class['stream'])->first();

            if ($stream === null) {
                $this->command?->warn(
                    "Skipped {$class['grade_level']}th section {$class['section']}: "
                    ."no stream with code {$class['stream']}."
                );

                continue;
            }

            ClassModel::updateOrCreate(
                [
                    'academic_session_id' => $session->id,
                    'grade_level' => $class['grade_level'],
                    'stream_id' => $stream->id,
                    'section' => $class['section'],
                ],
                [
                    'capacity' => $class['capacity'],
                    'room' => $class['room'],
                    'is_active' => true,
                ]
            );
        }
    }
}
