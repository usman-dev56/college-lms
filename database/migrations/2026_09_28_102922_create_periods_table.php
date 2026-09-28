<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the periods table.
     *
     * A period is one row of the daily timetable grid for an academic session:
     * "Period 1, 08:00-08:45" or "Break, 10:15-10:35". The number is the
     * grid's row order, which is why it is unique per session. Later stages
     * hang timetable slots off these rows.
     */
    public function up(): void
    {
        Schema::create('periods', function (Blueprint $table) {
            $table->id();

            // RESTRICT: deleting a session must never silently drop the
            // daily schedule the timetable was built on.
            $table->foreignId('academic_session_id')
                ->index()
                ->constrained('academic_sessions')
                ->restrictOnDelete();

            $table->smallInteger('number');
            $table->string('label', 30);

            // time, not datetime: a period is a clock time of day and carries
            // no date. The model keeps these as plain strings so no date is
            // ever invented for them.
            $table->time('start_time');
            $table->time('end_time');

            // Breaks stay in the grid so the timetable lines up, but they are
            // never offered when choosing a teaching slot.
            $table->boolean('is_break')->default(false);

            $table->timestamps();
            $table->softDeletes();
        });

        $driver = DB::connection()->getDriverName();

        // Laravel's schema builder cannot express CHECK constraints, and
        // SQLite does not support adding them through ALTER TABLE, so the
        // checks are applied on PostgreSQL (the application database) only.
        if ($driver === 'pgsql') {
            DB::statement(
                'ALTER TABLE periods ADD CONSTRAINT periods_number_check CHECK (number BETWEEN 1 AND 20)'
            );

            // A period must not end before it starts. Compared as strings,
            // HH:MM:SS sorts the same as it reads on a 24-hour clock.
            DB::statement(
                'ALTER TABLE periods ADD CONSTRAINT periods_time_order_check CHECK (end_time > start_time)'
            );
        }

        // A session cannot have two live periods in the same grid row, and
        // only among rows that have not been soft deleted - so a period that
        // was removed and added back is restored rather than duplicated.
        // Every column is NOT NULL, so a plain partial unique index is enough.
        DB::statement(
            'CREATE UNIQUE INDEX periods_session_number_unique '
            .'ON periods (academic_session_id, number) '
            .'WHERE deleted_at IS NULL'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('periods');
    }
};
