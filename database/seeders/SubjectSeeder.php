<?php

namespace Database\Seeders;

use App\Models\Stream;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    /**
     * Seed the subjects taught at Government College Chiniot, following the
     * BISE Faisalabad scheme of studies.
     *
     * Compulsory subjects (stream_id = NULL) are taken by every student.
     * Elective subjects belong to the stream that offers them and are also
     * linked through the stream_subject pivot table.
     *
     * Idempotent: subjects are matched on (name, grade_level, stream_id) and
     * pivot rows are added with syncWithoutDetaching.
     */
    public function run(): void
    {
        $this->seedCompulsorySubjects();
        $this->seedElectiveSubjects();
    }

    /**
     * Compulsory subjects: shared by every stream, so stream_id is NULL.
     */
    private function seedCompulsorySubjects(): void
    {
        $subjects = [
            ['name' => 'English', 'grade_level' => 11, 'code' => 'ENG', 'has_practical' => false],
            ['name' => 'Urdu', 'grade_level' => 11, 'code' => 'URD', 'has_practical' => false],
            ['name' => 'Islamiat', 'grade_level' => 11, 'code' => 'IED', 'has_practical' => false],
            ['name' => 'English', 'grade_level' => 12, 'code' => 'ENG', 'has_practical' => false],
            ['name' => 'Urdu', 'grade_level' => 12, 'code' => 'URD', 'has_practical' => false],
            ['name' => 'Pakistan Studies', 'grade_level' => 12, 'code' => 'PST', 'has_practical' => false],
        ];

        foreach ($subjects as $subject) {
            Subject::updateOrCreate(
                [
                    'name' => $subject['name'],
                    'grade_level' => $subject['grade_level'],
                    'stream_id' => null,
                ],
                [
                    'code' => $subject['code'],
                    'has_practical' => $subject['has_practical'],
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Elective subjects, grouped by the code of the stream offering them.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function electiveSubjects(): array
    {
        return [
            // Pre-Medical
            'PM' => [
                ['name' => 'Biology', 'grade_level' => 11, 'code' => 'BIO', 'has_practical' => true],
                ['name' => 'Physics', 'grade_level' => 11, 'code' => 'PHY', 'has_practical' => true],
                ['name' => 'Chemistry', 'grade_level' => 11, 'code' => 'CHM', 'has_practical' => true],
                ['name' => 'Biology', 'grade_level' => 12, 'code' => 'BIO', 'has_practical' => true],
                ['name' => 'Physics', 'grade_level' => 12, 'code' => 'PHY', 'has_practical' => true],
                ['name' => 'Chemistry', 'grade_level' => 12, 'code' => 'CHM', 'has_practical' => true],
            ],
            // Pre-Engineering
            'PE' => [
                ['name' => 'Physics', 'grade_level' => 11, 'code' => 'PHY', 'has_practical' => true],
                ['name' => 'Chemistry', 'grade_level' => 11, 'code' => 'CHM', 'has_practical' => true],
                ['name' => 'Mathematics', 'grade_level' => 11, 'code' => 'MTH', 'has_practical' => false],
                ['name' => 'Physics', 'grade_level' => 12, 'code' => 'PHY', 'has_practical' => true],
                ['name' => 'Chemistry', 'grade_level' => 12, 'code' => 'CHM', 'has_practical' => true],
                ['name' => 'Mathematics', 'grade_level' => 12, 'code' => 'MTH', 'has_practical' => false],
            ],
            // ICS (Intermediate in Computer Science)
            'ICS' => [
                ['name' => 'Computer Science', 'grade_level' => 11, 'code' => 'CSM', 'has_practical' => true],
                ['name' => 'Physics', 'grade_level' => 11, 'code' => 'PHY', 'has_practical' => true],
                ['name' => 'Mathematics', 'grade_level' => 11, 'code' => 'MTH', 'has_practical' => false],
                ['name' => 'Computer Science', 'grade_level' => 12, 'code' => 'CSM', 'has_practical' => true],
                ['name' => 'Physics', 'grade_level' => 12, 'code' => 'PHY', 'has_practical' => true],
                ['name' => 'Mathematics', 'grade_level' => 12, 'code' => 'MTH', 'has_practical' => false],
            ],
            // Commerce
            'COM' => [
                ['name' => 'Principles of Accounting', 'grade_level' => 11, 'code' => 'ACC', 'has_practical' => false],
                ['name' => 'Principles of Economics', 'grade_level' => 11, 'code' => 'ECO', 'has_practical' => false],
                ['name' => 'Principles of Commerce', 'grade_level' => 11, 'code' => 'COM', 'has_practical' => false],
                ['name' => 'Business Mathematics', 'grade_level' => 11, 'code' => 'BMT', 'has_practical' => false],
                ['name' => 'Commercial Geography', 'grade_level' => 12, 'code' => 'CGE', 'has_practical' => false],
                ['name' => 'Banking', 'grade_level' => 12, 'code' => 'BNK', 'has_practical' => false],
                ['name' => 'Statistics', 'grade_level' => 12, 'code' => 'STT', 'has_practical' => false],
            ],
            // Humanities
            'HUM' => [
                ['name' => 'Civics', 'grade_level' => 11, 'code' => 'CV', 'has_practical' => false],
                ['name' => 'Education', 'grade_level' => 11, 'code' => 'EDU', 'has_practical' => false],
                ['name' => 'Economics', 'grade_level' => 11, 'code' => 'ECO', 'has_practical' => false],
                ['name' => 'Islamic Studies', 'grade_level' => 11, 'code' => 'IST', 'has_practical' => false],
                ['name' => 'Fine Arts', 'grade_level' => 11, 'code' => 'FAR', 'has_practical' => true],
                ['name' => 'Psychology', 'grade_level' => 12, 'code' => 'PSY', 'has_practical' => true],
                ['name' => 'Sociology', 'grade_level' => 12, 'code' => 'SOC', 'has_practical' => false],
                ['name' => 'Geography', 'grade_level' => 12, 'code' => 'GEO', 'has_practical' => true],
                ['name' => 'History', 'grade_level' => 12, 'code' => 'HIS', 'has_practical' => false],
                ['name' => 'Health & Physical Education', 'grade_level' => 12, 'code' => 'HPE', 'has_practical' => false],
            ],
        ];
    }

    /**
     * Elective subjects belong to a stream, so each one is stored with that
     * stream in stream_id and is also linked to it in the stream_subject
     * pivot table.
     */
    private function seedElectiveSubjects(): void
    {
        foreach ($this->electiveSubjects() as $streamCode => $subjects) {
            $stream = Stream::query()->where('code', $streamCode)->first();

            if ($stream === null) {
                $this->command?->warn(
                    "Skipped elective subjects for stream {$streamCode}: no stream with that code."
                );

                continue;
            }

            foreach ($subjects as $subject) {
                $model = Subject::updateOrCreate(
                    [
                        'name' => $subject['name'],
                        'grade_level' => $subject['grade_level'],
                        'stream_id' => $stream->id,
                    ],
                    [
                        'code' => $subject['code'],
                        'has_practical' => $subject['has_practical'],
                        'is_active' => true,
                    ]
                );

                $model->streams()->syncWithoutDetaching([$stream->id]);
            }
        }
    }
}
