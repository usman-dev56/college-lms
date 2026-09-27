<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use Illuminate\Database\Seeder;

class AcademicSessionSeeder extends Seeder
{
    /**
     * Seed the initial academic session.
     *
     * Creates the 2026-2027 session and marks it active if no other
     * session is active. Idempotent: running this twice will not
     * create duplicates.
     */
    public function run(): void
    {
        $session = AcademicSession::updateOrCreate(
            ['name' => '2026-2027'],
            [
                'start_date' => '2026-08-01',
                'end_date' => '2027-05-31',
            ]
        );

        if (AcademicSession::active()->doesntExist()) {
            $session->activate();
        }
    }
}