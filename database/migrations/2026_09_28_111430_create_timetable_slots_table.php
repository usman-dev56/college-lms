<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the timetable_slots table.
     *
     * One row is one cell of a class's weekly grid: "11th Pre-Medical A has
     * Physics with Kamran Tariq on Monday, period 5, room R-101". The grid is
     * (day_of_week x period) for a single class, so those three columns are
     * unique among live rows.
     *
     * The class_subject is the pivot, not the subject, because the teacher is
     * what the grid has to keep unique - the same subject is taught by
     * different teachers in different classes.
     */
    public function up(): void
    {
        Schema::create('timetable_slots', function (Blueprint $table) {
            $table->id();

            // CASCADE: a hard-deleted class takes its grid with it.
            $table->foreignId('class_id')
                ->index()
                ->constrained('classes')
                ->cascadeOnDelete();

            // RESTRICT on both of these: a period or an assignment that is
            // still on a timetable must never be removed silently. The
            // periods module checks for this before deleting a period.
            $table->foreignId('period_id')
                ->index()
                ->constrained('periods')
                ->restrictOnDelete();

            $table->smallInteger('day_of_week');
            $table->index('day_of_week');

            $table->foreignId('class_subject_id')
                ->index()
                ->constrained('class_subjects')
                ->restrictOnDelete();

            // Reserved for later: the UI does not set a room yet, so this
            // stays null and the class's own room applies.
            $table->string('room', 50)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        $driver = DB::connection()->getDriverName();

        // Laravel's schema builder cannot express CHECK constraints, and
        // SQLite does not support adding them through ALTER TABLE, so the
        // check is applied on PostgreSQL (the application database) only.
        if ($driver === 'pgsql') {
            DB::statement(
                'ALTER TABLE timetable_slots ADD CONSTRAINT timetable_slots_day_of_week_check CHECK (day_of_week BETWEEN 1 AND 6)'
            );
        }

        // A class can only occupy each grid cell once, and only among rows
        // that have not been soft deleted - so rebuilding a timetable replaces
        // its rows instead of colliding with them. Every column is NOT NULL,
        // so a plain partial unique index is enough.
        DB::statement(
            'CREATE UNIQUE INDEX timetable_slots_class_period_day_unique '
            .'ON timetable_slots (class_id, period_id, day_of_week) '
            .'WHERE deleted_at IS NULL'
        );
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('timetable_slots');
    }
};
