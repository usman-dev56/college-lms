<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\Period;
use Illuminate\Database\Seeder;

class PeriodSeeder extends Seeder
{
    /**
     * Seed the daily timetable grid for the active session.
     *
     * A standard college morning: seven teaching periods split by a short
     * break and a lunch. The numbers are grid rows, which is why a break can
     * sit at row 4 without being "period 4".
     *
     * Idempotent: rows are matched on (academic_session_id, number), and
     * soft-deleted rows are matched too so a period an admin removed earlier
     * is restored rather than duplicated.
     */
    public function run(): void
    {
        $session = AcademicSession::active()->first();

        if ($session === null) {
            $this->command?->warn('Skipped periods: there is no active academic session.');

            return;
        }

        $periods = [
            ['number' => 1, 'label' => 'Period 1', 'start_time' => '08:00', 'end_time' => '08:45', 'is_break' => false],
            ['number' => 2, 'label' => 'Period 2', 'start_time' => '08:45', 'end_time' => '09:30', 'is_break' => false],
            ['number' => 3, 'label' => 'Period 3', 'start_time' => '09:30', 'end_time' => '10:15', 'is_break' => false],
            ['number' => 4, 'label' => 'Break', 'start_time' => '10:15', 'end_time' => '10:35', 'is_break' => true],
            ['number' => 5, 'label' => 'Period 4', 'start_time' => '10:35', 'end_time' => '11:20', 'is_break' => false],
            ['number' => 6, 'label' => 'Period 5', 'start_time' => '11:20', 'end_time' => '12:05', 'is_break' => false],
            ['number' => 7, 'label' => 'Lunch', 'start_time' => '12:05', 'end_time' => '12:35', 'is_break' => true],
            ['number' => 8, 'label' => 'Period 6', 'start_time' => '12:35', 'end_time' => '13:20', 'is_break' => false],
            ['number' => 9, 'label' => 'Period 7', 'start_time' => '13:20', 'end_time' => '14:05', 'is_break' => false],
        ];

        foreach ($periods as $period) {
            $model = Period::withTrashed()->updateOrCreate(
                [
                    'academic_session_id' => $session->id,
                    'number' => $period['number'],
                ],
                [
                    'label' => $period['label'],
                    'start_time' => $period['start_time'],
                    'end_time' => $period['end_time'],
                    'is_break' => $period['is_break'],
                ]
            );

            if ($model->trashed()) {
                $model->restore();
            }
        }

        $breaks = count(array_filter($periods, fn (array $p) => $p['is_break']));

        $this->command?->info(sprintf(
            'Ensured %d periods (%d teaching, %d breaks) for %s.',
            count($periods),
            count($periods) - $breaks,
            $breaks,
            $session->name
        ));
    }
}
