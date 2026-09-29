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
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();

            // The student. CASCADE, unlike the other two: an enrollment has
            // no meaning without the student, and deleting a student should
            // take their enrollments with it rather than leave orphans
            // pointing at a profile that no longer exists.
            $table->foreignId('student_profile_id')
                ->constrained('student_profiles')
                ->cascadeOnDelete();

            // The class they sit in. RESTRICT: a class with students on it is
            // a class the office needs to see before deleting.
            $table->foreignId('class_id')
                ->constrained('classes')
                ->restrictOnDelete();

            // Denormalised from the class, deliberately. A class belongs to
            // exactly one session, so this is always the class's own session
            // - but the "one class per student per session" rule can only be
            // enforced by an index that does not have to join first, and that
            // is the rule most likely to be broken by a bug elsewhere.
            $table->foreignId('academic_session_id')
                ->constrained('academic_sessions')
                ->restrictOnDelete();

            $table->date('enrolled_at');

            // 'transferred' is for a student moved to another class in the
            // same session; 'withdrawn' is for one who left. Both keep the
            // row so the history of where somebody used to sit survives.
            $table->string('status', 20)->default('active')->index();

            $table->timestamps();
            $table->softDeletes();
        });

        $driver = DB::connection()->getDriverName();

        // Laravel's schema builder cannot express a CHECK constraint, and
        // SQLite does not support adding one through ALTER TABLE, so it is
        // applied on PostgreSQL (the application database) only.
        if ($driver === 'pgsql') {
            DB::statement(
                "ALTER TABLE enrollments ADD CONSTRAINT enrollments_status_check CHECK (status IN ('active', 'transferred', 'withdrawn'))"
            );
        }

        /*
            The rule this table exists to enforce: a student has at most one
            class per session.

            Partial on deleted_at, which is what makes a move possible. When
            a student is moved to a different class in the same session the
            old enrollment is soft deleted and a new row is written, so the
            old number is freed and the history is kept. A full unique index
            would make the second insert impossible, and there would then be
            no way to record a move at all.
        */
        DB::statement(
            'CREATE UNIQUE INDEX enrollments_student_session_unique ON enrollments (student_profile_id, academic_session_id) WHERE deleted_at IS NULL'
        );

        // The roster page reads "who is in this class" on every visit, and
        // the session filter is applied alongside it, so class_id carries an
        // index of its own.
        DB::statement(
            'CREATE INDEX enrollments_class_id_index ON enrollments (class_id)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
