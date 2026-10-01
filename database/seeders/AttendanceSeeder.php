<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\Period;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Seed a term of historical attendance for the students already enrolled.
 *
 * Attendance is a legal record, so this seeder is deliberately conservative in
 * two directions. It only marks students who have a live enrollment in the
 * active session, because attendance for somebody who was never in a class
 * would be a fabrication. And it stops at yesterday, because today is the one
 * day the teachers have to mark themselves.
 *
 * Idempotent: rows are matched on the same four columns the partial unique
 * index covers, and soft-deleted rows are matched and restored rather than
 * duplicated - a withdrawn mark an admin removed earlier is reinstated
 * instead of colliding with the index, which is the same rule the class
 * subject seeder follows.
 */
class AttendanceSeeder extends Seeder
{
    /**
     * How many days back to seed. Thirty is a working month, which is enough
     * for a percentage to mean something and short enough to keep the seed
     * quick.
     */
    private const DAYS = 30;

    /**
     * How many enrollments to commit at a time.
     *
     * Small enough that the lock footprint of one transaction stays well under
     * PostgreSQL's limit, large enough that the per-transaction overhead is
     * not what dominates the run.
     */
    private const CHUNK = 2;

    /**
     * How many of the hundred weighted picks each status gets.
     *
     * 80/10/5/5 gives the ratio exactly rather than approximately, and
     * holding it as weights in one array keeps it readable - the alternative,
     * four nested random() comparisons, hides the ratio in control flow where
     * nobody can check it sums to a hundred.
     *
     * @var array<string, int>
     */
    private const STATUS_WEIGHTS = [
        Attendance::STATUS_PRESENT => 80,
        Attendance::STATUS_ABSENT => 10,
        Attendance::STATUS_LATE => 5,
        Attendance::STATUS_LEAVE => 5,
    ];

    /**
     * Seed the register.
     *
     * Every dependency is checked and reported rather than assumed: an empty
     * database should produce a clear message, not an attendance table full of
     * rows about nobody.
     */
    public function run(): void
    {
        $session = AcademicSession::current();

        if ($session === null) {
            $this->command?->warn('Skipped attendance: there is no active academic session.');

            return;
        }

        $enrollments = Enrollment::query()
            ->where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->with('classModel.classSubjects')
            ->get();

        if ($enrollments->isEmpty()) {
            $this->command?->warn(
                'Skipped attendance: no students are enrolled in the active session, '
                .'so there is nobody to mark. Enroll students first.'
            );

            return;
        }

        // Teaching periods only: a break is a row in the grid so the timetable
        // lines up, and nothing is taught in it.
        $periods = Period::teachingForSession($session->id);

        if ($periods->isEmpty()) {
            $this->command?->warn(
                'Skipped attendance: the active session has no teaching periods '
                .'(seed the periods first with PeriodSeeder).'
            );

            return;
        }

        $dates = $this->schoolDates();

        if ($dates->isEmpty()) {
            $this->command?->warn('Skipped attendance: there are no school days to seed.');

            return;
        }

        $seeded = 0;
        $seeded = 0;

        /*
            Committed in chunks rather than in one transaction.

            A term is tens of thousands of rows, and a single transaction that
            size holds a lock on every one of the eight indexes for the whole
            run. PostgreSQL runs out of shared memory part way through and
            fails with 53200 - which is a property of the volume, not of the
            code, and would happen again on a larger roll.

            Chunking trades the all-or-nothing property for one that matters
            more here: a chunk that fails leaves earlier chunks committed, and
            because every write is an updateOrCreate keyed on the same four
            columns, re-running picks up exactly where it stopped instead of
            duplicating or tripping the unique index. An interrupted seed is
            recoverable; a seed that cannot finish at all is not.
        */
        foreach ($enrollments->chunk(self::CHUNK) as $chunk) {
            DB::transaction(function () use ($chunk, $periods, $dates, &$seeded): void {
                foreach ($chunk as $enrollment) {
                    $class = $enrollment->classModel;

                    if ($class === null) {
                        continue;
                    }

                    foreach ($class->classSubjects as $classSubject) {
                        foreach ($dates as $date) {
                            foreach ($periods as $period) {
                                $this->mark(
                                    $enrollment->student_profile_id,
                                    $classSubject,
                                    $period,
                                    $date,
                                );

                                $seeded++;
                            }
                        }
                    }
                }
            });
        }

        $this->command?->info(sprintf(
            'Seeded %d attendance records for %d students across %d days.',
            $seeded,
            $enrollments->count(),
            $dates->count(),
        ));
    }

    /**
     * The teaching days to seed: yesterday back thirty days, Sundays skipped.
     *
     * Today is excluded on purpose. It is the one day a teacher is still
     * expected to mark, and seeding it would hand them a register that
     * already claims to be complete for a period they have not taught yet.
     *
     * Sunday is ISO day 7, the one day outside the Monday-to-Saturday school
     * week the timetable grid is built on.
     *
     * @return Collection<int, Carbon>
     */
    private function schoolDates(): Collection
    {
        $dates = collect();
        $today = Carbon::today();

        for ($daysAgo = 1; $daysAgo <= self::DAYS; $daysAgo++) {
            $date = $today->copy()->subDays($daysAgo);

            // dayOfWeekIso is ISO-8601: 1 = Monday .. 7 = Sunday. Compared
            // against the literal 7 rather than CarbonInterface::SUNDAY,
            // which is 0 - that constant follows PHP's date() numbering
            // (0 = Sunday) and so never matches an ISO weekday.
            if ($date->dayOfWeekIso === 7) {
                continue;
            }

            $dates->push($date);
        }

        return $dates;
    }

    /**
     * Write one mark, or update the one already there.
     *
     * Matched on the four columns the partial unique index covers, which is
     * the only correct key: a period is identified by the student, the
     * subject, the slot in the day and the date, and matching on fewer of them
     * would overwrite a different period's mark.
     */
    private function mark(
        int $studentProfileId,
        ClassSubject $classSubject,
        Period $period,
        Carbon $date,
    ): void {
        $attendance = Attendance::withTrashed()->updateOrCreate(
            [
                'student_profile_id' => $studentProfileId,
                'class_subject_id' => $classSubject->id,
                'period_id' => $period->id,
                'attendance_date' => $date->toDateString(),
            ],
            [
                'status' => $this->randomStatus(),

                // The teacher who owns the assignment, not whoever happens to
                // run the seeder: the register has to name the person who can
                // answer for the mark.
                'marked_by' => $classSubject->teacher_id,

                /*
                 * Marked at the moment the period ended, not when this row was
                 * written. A register filled in afterwards is normal, and a
                 * marked_at of "whenever the seeder happened to run" would
                 * make every historical row look like it was written at once,
                 * which is both untrue and useless for an audit.
                 */
                'marked_at' => Carbon::parse(
                    $date->toDateString().' '.$period->end_time->format('H:i'),
                ),
                'notes' => null,
            ],
        );

        // A mark an admin withdrew earlier is reinstated rather than left
        // invisible, otherwise the re-run would silently do nothing for it.
        if ($attendance->trashed()) {
            $attendance->restore();
        }
    }

    /**
     * One status, picked by weight.
     *
     * Built as a 100-entry list per call rather than precomputed in a
     * constant, because PHP does not allow array_fill() inside a class
     * constant expression. random_int() is used rather than fake() so the
     * distribution does not depend on a seeded global generator state.
     */
    private function randomStatus(): string
    {
        $pool = [];

        foreach (self::STATUS_WEIGHTS as $status => $weight) {
            for ($i = 0; $i < $weight; $i++) {
                $pool[] = $status;
            }
        }

        return $pool[random_int(0, count($pool) - 1)];
    }
}
