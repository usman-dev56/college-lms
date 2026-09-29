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
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();

            // The login half of a student. A student is a User with the
            // Student role, exactly as a teacher is a User with the Teacher
            // role, so the two are split the same way: accounts in users,
            // role-specific detail here.
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            // The cohort the student was admitted with. Not the academic
            // session: a batch spans several sessions, and a student stays on
            // one batch from admission to graduation.
            $table->foreignId('batch_id')
                ->constrained('student_batches')
                ->restrictOnDelete();

            // The roll number is unique per batch, not globally: 001 in
            // 2026-2028 and 001 in 2025-2027 are two different students. See
            // the partial unique index below, which enforces exactly that.
            $table->string('roll_number', 20);

            // BISE Faisalabad registration number. Assigned by the board, so
            // many students will not have one yet.
            $table->string('board_registration_number', 30)->nullable();

            // CNIC for adults, B-Form number for minors. B-Form numbers are
            // issued to children, so this column holds either.
            $table->string('cnic_bform', 20)->nullable();

            $table->date('date_of_birth')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('father_name', 100)->nullable();

            // The guardian phone is separate from users.phone: a student's own
            // number is often a school SIM or not held at all, and the
            // guardian is who the college calls.
            $table->string('guardian_phone', 20)->nullable();

            $table->string('address', 255)->nullable();

            // Reserved for a future photo upload. Nothing writes it yet, so
            // it stays null until the storage layer is built.
            $table->string('photo_path', 255)->nullable();

            $table->date('admission_date')->nullable();
            $table->string('previous_school', 150)->nullable();

            // Matric result on admission. The two are stored as a pair so a
            // percentage can be worked out later without having to know what
            // the total was for that year.
            $table->integer('previous_marks_obtained')->nullable();
            $table->integer('previous_marks_total')->nullable();

            // A student stays on the books after leaving: 'graduated' and
            // 'withdrawn' are both terminal records rather than deletions, so
            // the row is kept and only the status changes.
            $table->string('status', 20)->default('active')->index();

            $table->timestamps();
            $table->softDeletes();
        });

        $driver = DB::connection()->getDriverName();

        // Laravel's schema builder cannot express a CHECK constraint, and
        // SQLite does not support adding one through ALTER TABLE, so both
        // are applied on PostgreSQL (the application database) only.
        if ($driver === 'pgsql') {
            DB::statement(
                "ALTER TABLE student_profiles ADD CONSTRAINT student_profiles_gender_check CHECK (gender IS NULL OR gender IN ('male', 'female', 'other'))"
            );

            DB::statement(
                'ALTER TABLE student_profiles ADD CONSTRAINT student_profiles_previous_marks_obtained_check CHECK (previous_marks_obtained IS NULL OR previous_marks_obtained >= 0)'
            );

            DB::statement(
                'ALTER TABLE student_profiles ADD CONSTRAINT student_profiles_previous_marks_total_check CHECK (previous_marks_total IS NULL OR previous_marks_total > 0)'
            );

            DB::statement(
                "ALTER TABLE student_profiles ADD CONSTRAINT student_profiles_status_check CHECK (status IN ('active', 'graduated', 'withdrawn', 'suspended'))"
            );
        }

        // Roll numbers are unique per batch, not globally. Laravel's
        // ->unique() cannot express a WHERE clause, so the DDL is written by
        // hand, exactly as the other tables do it. The index is partial so a
        // soft-deleted student frees their roll number for reuse, which is
        // also what lets a student who left be admitted again later.
        DB::statement(
            'CREATE UNIQUE INDEX student_profiles_batch_roll_number_unique ON student_profiles (batch_id, roll_number) WHERE deleted_at IS NULL'
        );

        // A CNIC identifies one person, so two live students cannot share
        // one. The IS NOT NULL half matters as much as the partial half: a
        // student with no CNIC on file must not be blocked at all, and
        // saying so in the index states that outright rather than relying on
        // Postgres treating NULLs as distinct.
        DB::statement(
            'CREATE UNIQUE INDEX student_profiles_cnic_bform_unique ON student_profiles (cnic_bform) WHERE deleted_at IS NULL AND cnic_bform IS NOT NULL'
        );

        // One profile per user. Partial for the same reason as the roll
        // number: deleting a student soft-deletes the profile too, so the
        // account can be given a new profile on a later admission.
        DB::statement(
            'CREATE UNIQUE INDEX student_profiles_user_id_unique ON student_profiles (user_id) WHERE deleted_at IS NULL'
        );

        // The students list is ordered and filtered by batch, which is a
        // different access path from the user_id lookups the account pages
        // do, so batch_id gets its own plain index.
        DB::statement(
            'CREATE INDEX student_profiles_batch_id_index ON student_profiles (batch_id)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
