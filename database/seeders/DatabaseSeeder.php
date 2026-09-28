<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * This is the entry point for all database seeding.
     * Add new seeders via $this->call(...) below.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            AcademicSessionSeeder::class,
            PeriodSeeder::class,
            StreamSeeder::class,
            SubjectSeeder::class,
            ClassSeeder::class,
            TeacherSeeder::class,
            ClassSubjectSeeder::class,
            StudentBatchSeeder::class,
        ]);
    }
}
