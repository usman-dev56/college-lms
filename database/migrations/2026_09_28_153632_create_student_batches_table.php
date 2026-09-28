<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('student_batches', function (Blueprint $table) {
            $table->id();

            // The cohort, e.g. "2026-2028": admitted in 2026, expected to
            // graduate in 2028. This is deliberately a separate table from
            // academic_sessions, which is the operational year the college is
            // running. One session is active at a time; several batches are
            // active at once, because each one is a different year group.
            $table->string('name', 30);

            // The college is intermediate, so a cohort can start at grade 9
            // and still graduate at 12. The default is 11, the usual intake.
            $table->smallInteger('start_grade')->default(11);

            $table->smallInteger('expected_graduation_year');

            $table->boolean('is_active')->default(true)->index();
            $table->string('notes', 255)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        $driver = DB::connection()->getDriverName();

        // Laravel's schema builder cannot express a CHECK constraint, and
        // SQLite does not support adding one through ALTER TABLE, so both
        // are applied on PostgreSQL (the application database) only.
        if ($driver === 'pgsql') {
            DB::statement(
                'ALTER TABLE student_batches ADD CONSTRAINT student_batches_start_grade_check CHECK (start_grade BETWEEN 9 AND 12)'
            );

            DB::statement(
                'ALTER TABLE student_batches ADD CONSTRAINT student_batches_expected_graduation_year_check CHECK (expected_graduation_year BETWEEN 2020 AND 2100)'
            );
        }

        // One live batch per cohort, and a soft-deleted one frees its name for
        // reuse. Laravel's ->unique() cannot express a WHERE clause, so the
        // DDL is written by hand, exactly as the other tables do it.
        DB::statement(
            'CREATE UNIQUE INDEX student_batches_name_unique ON student_batches (name) WHERE deleted_at IS NULL'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_batches');
    }
};
