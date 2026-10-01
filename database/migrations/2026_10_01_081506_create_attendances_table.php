<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the attendances table.
     *
     * One row is one student's status for one class-subject, in one period, on
     * one date: "Ahmed Khan was absent for 11th Pre-Medical A Physics, period 3,
     * on 12 October". The granularity is deliberately per period rather than
     * per day, because that is the unit the timetable is taught in and the unit
     * a register sheet has a row for.
     *
     * The record hangs off class_subjects rather than off the class directly,
     * because a class-subject is what knows which subject was taught and which
     * teacher owned it. One teacher marks one subject in one class, so the
     * teacher is derivable from the row rather than stored twice.
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();

            // RESTRICT on every foreign key here, unlike most of the schema.
            // Attendance is the evidence behind board eligibility, so it must
            // never disappear because a related row was changed or removed. A
            // cascade would let a single deleted student quietly erase a term
            // of legal records; the office has to make that decision
            // deliberately instead.
            $table->foreignId('student_profile_id')
                ->constrained('student_profiles')
                ->restrictOnDelete();

            $table->foreignId('class_subject_id')
                ->constrained('class_subjects')
                ->restrictOnDelete();

            $table->foreignId('period_id')
                ->constrained('periods')
                ->restrictOnDelete();

            $table->date('attendance_date');

            $table->string('status', 10);

            // Who wrote the register, kept forever even if that teacher
            // account is later deactivated: the college has to be able to say
            // who marked a disputed period.
            $table->foreignId('marked_by')
                ->constrained('users')
                ->restrictOnDelete();

            // A separate timestamp from created_at: a register can be marked
            // up after the fact (a teacher filling in a forgotten sheet), and
            // "when the period happened" and "when the row was written" are
            // genuinely different questions.
            $table->timestamp('marked_at');

            $table->string('notes', 255)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        $driver = DB::connection()->getDriverName();

        // Laravel's schema builder cannot express a CHECK constraint, and
        // SQLite does not support adding one through ALTER TABLE, so it is
        // applied on PostgreSQL (the application database) only. The column
        // is a short string rather than an enum so that a new status can be
        // introduced without a schema migration.
        if ($driver === 'pgsql') {
            DB::statement(
                "ALTER TABLE attendances ADD CONSTRAINT attendances_status_check CHECK (status IN ('present', 'absent', 'late', 'leave'))"
            );
        }

        /*
            The rule this table exists to enforce: a student has exactly one
            status per class-subject, per period, per date.

            Partial on deleted_at, so a mistaken mark can be withdrawn and the
            period re-marked. That is an admin action in 4.4, and it is the
            reason a soft delete rather than a hard delete is used here.

            The session is not part of the key because a class-subject already
            belongs to exactly one session: the date plus the class-subject
            identifies the teaching day unambiguously.
        */
        DB::statement(
            'CREATE UNIQUE INDEX attendances_student_class_subject_period_date_unique '
            .'ON attendances (student_profile_id, class_subject_id, period_id, attendance_date) '
            .'WHERE deleted_at IS NULL'
        );

        /*
            Single-column indexes for the four ways a register is read: one
            day across the whole college (the date), one subject's register
            (class_subject_id), one student's history (student_profile_id),
            and one period of one day (period_id).

            marked_by is indexed because "who marked this period" is an audit
            question asked of a specific teacher over a range of dates.
        */
        DB::statement('CREATE INDEX attendances_attendance_date_index ON attendances (attendance_date)');
        DB::statement('CREATE INDEX attendances_class_subject_id_index ON attendances (class_subject_id)');
        DB::statement('CREATE INDEX attendances_student_profile_id_index ON attendances (student_profile_id)');
        DB::statement('CREATE INDEX attendances_period_id_index ON attendances (period_id)');
        DB::statement('CREATE INDEX attendances_marked_by_index ON attendances (marked_by)');

        /*
            The composite index does the work the class + date queries
            actually need, and it does it without the planner having to
            intersect the two single-column indexes above.
        */
        DB::statement(
            'CREATE INDEX attendances_class_subject_date_index ON attendances (class_subject_id, attendance_date)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
