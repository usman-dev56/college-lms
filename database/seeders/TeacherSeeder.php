<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TeacherSeeder extends Seeder
{
    /**
     * Create the teaching staff of the college.
     *
     * These accounts exist so the assignment form has someone to assign and
     * so a teacher can sign in. The shared password is for local development
     * only.
     *
     * Idempotent: teachers are matched on email, so re-running refreshes the
     * role and active flag without creating duplicates.
     */
    public function run(): void
    {
        $teachers = [
            ['name' => 'Ahmed Khan', 'email' => 'ahmed.khan@college.test'],
            ['name' => 'Fatima Ali', 'email' => 'fatima.ali@college.test'],
            ['name' => 'Imran Sheikh', 'email' => 'imran.sheikh@college.test'],
            ['name' => 'Sana Malik', 'email' => 'sana.malik@college.test'],
            ['name' => 'Bilal Ahmad', 'email' => 'bilal.ahmad@college.test'],
            ['name' => 'Zara Hussain', 'email' => 'zara.hussain@college.test'],
            ['name' => 'Kamran Tariq', 'email' => 'kamran.tariq@college.test'],
            ['name' => 'Nadia Iqbal', 'email' => 'nadia.iqbal@college.test'],
        ];

        foreach ($teachers as $teacher) {
            User::updateOrCreate(
                ['email' => $teacher['email']],
                [
                    'name' => $teacher['name'],
                    'password' => Hash::make('teacher123'),
                    'role' => UserRole::Teacher,
                    'is_active' => true,
                ]
            );
        }

        $this->command?->info('Ensured '.count($teachers).' teachers.');
    }
}
