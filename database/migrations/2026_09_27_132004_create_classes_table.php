<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the classes table.
     *
     * A class is one section of students in a grade and stream for a single
     * academic session, e.g. "11th Pre-Medical A" of 2026-2027. Attendance,
     * timetables, enrolments and assessments will hang off a class.
     */
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();

            // RESTRICT, not cascade or set null: deleting a session or a
            // stream must never silently remove classes. The admin has to
            // deal with the classes first.
            $table->foreignId('academic_session_id')
                ->index()
                ->constrained('academic_sessions')
                ->restrictOnDelete();

            $table->foreignId('stream_id')
                ->index()
                ->constrained('streams')
                ->restrictOnDelete();

            $table->smallInteger('grade_level');
            $table->string('section', 10);
            $table->smallInteger('capacity')->nullable();
            $table->string('room', 50)->nullable();
            $table->boolean('is_active')->default(true)->index();

            $table->index('grade_level');

            $table->timestamps();
            $table->softDeletes();
        });

        $driver = DB::connection()->getDriverName();

        // Laravel's schema builder cannot express CHECK constraints, and
        // SQLite does not support adding them through ALTER TABLE, so both
        // are applied on PostgreSQL (the application database) only.
        if ($driver === 'pgsql') {
            DB::statement(
                'ALTER TABLE classes ADD CONSTRAINT classes_grade_level_check CHECK (grade_level IN (11, 12))'
            );

            DB::statement(
                'ALTER TABLE classes ADD CONSTRAINT classes_capacity_check CHECK (capacity > 0)'
            );
        }

        // Two classes cannot share the same section of a grade and stream
        // within one session, and only among rows that have not been soft
        // deleted. Every column is NOT NULL, so a plain partial unique index
        // is enough (no NULLS NOT DISTINCT needed).
        DB::statement(
            'CREATE UNIQUE INDEX classes_session_grade_stream_section_unique '
            .'ON classes (academic_session_id, grade_level, stream_id, section) '
            .'WHERE deleted_at IS NULL'
        );
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
