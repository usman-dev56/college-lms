<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\ClassModel;
use App\Models\ClassSubject;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class ClassSubjectSeeder extends Seeder
{
    /**
     * Give every class of the active session a full set of teaching
     * assignments, so the assignment form and any later timetable work have
     * realistic data behind them.
     *
     * Teachers are handed out in rotation rather than randomly, so re-running
     * the seeder produces the same spread of teaching loads and no teacher
     * ends up with every class.
     *
     * Idempotent: rows are matched on (class_id, subject_id). Soft-deleted
     * rows are matched too, so a subject an admin removed earlier is restored
     * rather than duplicated - the same rule the admin form follows.
     */
    public function run(): void
    {
        $session = AcademicSession::active()->first();

        if ($session === null) {
            $this->command?->warn('Skipped class subjects: there is no active academic session.');

            return;
        }

        $teachers = User::query()
            ->where('role', 'teacher')
            ->orderBy('name')
            ->get();

        if ($teachers->isEmpty()) {
            $this->command?->warn('Skipped class subjects: seed the teachers first (TeacherSeeder).');

            return;
        }

        $classes = ClassModel::forSession($session->id)
            ->orderBy('grade_level')
            ->orderBy('stream_id')
            ->orderBy('section')
            ->get();

        foreach ($classes->values() as $classIndex => $class) {
            $subjects = $this->availableSubjects($class);
            $assigned = 0;

            foreach ($subjects->values() as $subjectIndex => $subject) {
                $teacher = $teachers[($classIndex + $subjectIndex) % $teachers->count()];

                $assignment = ClassSubject::withTrashed()->updateOrCreate(
                    [
                        'class_id' => $class->id,
                        'subject_id' => $subject->id,
                    ],
                    [
                        'teacher_id' => $teacher->id,
                        'periods_per_week' => $this->periodsPerWeek($subject),
                    ]
                );

                if ($assignment->trashed()) {
                    $assignment->restore();
                }

                $assigned++;
            }

            $this->command?->info(
                "Assigned {$assigned} subjects to {$class->displayName()}"
            );
        }
    }

    /**
     * The subjects taught in this class: the compulsory subjects of its grade
     * level plus the electives of its stream.
     *
     * @return Collection<int, Subject>
     */
    private function availableSubjects(ClassModel $class): Collection
    {
        return Subject::active()
            ->where('grade_level', $class->grade_level)
            ->where(function ($query) use ($class) {
                $query->whereNull('stream_id')
                    ->orWhere('stream_id', $class->stream_id);
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Weekly periods for a subject, following the BISE Faisalabad scheme.
     *
     * Names are matched exactly because the commerce and humanities streams
     * both offer similarly named subjects (Principles of Economics in
     * commerce, Economics in humanities).
     */
    private function periodsPerWeek(Subject $subject): int
    {
        return match ($subject->name) {
            'English' => 6,
            'Urdu' => 5,
            'Islamiat', 'Pakistan Studies' => 2,
            'Physics', 'Chemistry', 'Biology', 'Computer Science' => 7,
            'Mathematics' => 6,
            'Principles of Accounting',
            'Principles of Economics',
            'Principles of Commerce',
            'Business Mathematics',
            'Commercial Geography',
            'Banking',
            'Statistics' => 5,
            'Civics',
            'Education',
            'Economics',
            'Islamic Studies',
            'Fine Arts',
            'Psychology',
            'Sociology',
            'Geography',
            'History',
            'Health & Physical Education' => 4,
            default => 5,
        };
    }
}
