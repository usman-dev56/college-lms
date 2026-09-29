<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * An admission is an application to study, not a student. Somebody who
     * has never enrolled can have a row here, which is what makes the table
     * different from student_profiles: that one is a login plus a record of
     * somebody who already studies here.
     *
     * The two are linked only once, at the end, through
     * enrolled_student_profile_id - set when an accepted application is
     * turned into a student in sub-stage 3.5. Keeping the link one-way and
     * nullable means an application can exist, be rejected, or be withdrawn
     * without any of that touching the student roll.
     */
    public function up(): void
    {
        Schema::create('admissions', function (Blueprint $table) {
            $table->id();

            // The number the applicant is given and quotes back. It is the
            // only way to find an application again, since there is no login
            // behind it, so it is unique and printed on the receipt.
            $table->string('application_number', 30);

            // The applicant's own details, typed on a public form and not yet
            // verified by anyone. Nothing here is trusted beyond the format
            // checks in the form request.
            $table->string('applicant_name', 100);
            $table->string('father_name', 100)->nullable();

            // Required here, unlike on a student profile. An application
            // without a CNIC or B-Form cannot be de-duplicated against the
            // school's roll, which is the main reason to ask for it.
            $table->string('cnic_bform', 20);

            $table->date('date_of_birth')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('guardian_phone', 20)->nullable();
            $table->string('address', 255)->nullable();

            // Matric result, carried over into the student profile on
            // acceptance. The total is per-year and not fixed at 1100
            // forever, so it is stored rather than assumed.
            $table->string('previous_school', 150)->nullable();
            $table->integer('previous_marks_obtained')->nullable();
            $table->integer('previous_marks_total')->nullable();

            // The stream the applicant is asking for. A stream, not a class:
            // a class belongs to one academic session, and an application may
            // sit unanswered for longer than a session lasts.
            $table->foreignId('stream_applied_id')
                ->constrained('streams')
                ->restrictOnDelete();

            // The cohort they would join if accepted. Restrict, not cascade:
            // a batch with applications against it is a batch the office
            // needs to see before it goes.
            $table->foreignId('batch_id')
                ->constrained('student_batches')
                ->restrictOnDelete();

            // pending -> reviewed -> accepted/rejected, then accepted ->
            // enrolled once a student record exists. Reviewed is separate
            // from accepted so the office can mark an application as seen
            // before anyone has decided on it.
            $table->string('status', 20)->default('pending')->index();

            // Where the applicant ranked on merit. Null until a decision is
            // being made, and not necessarily unique: ties are real.
            $table->integer('merit_rank')->nullable();

            $table->timestamp('reviewed_at')->nullable();

            // Which admin looked at it. Set null if that account is ever
            // removed: the review still happened, and losing the record of
            // it would be worse than losing the name.
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('rejection_reason', 255)->nullable();

            // Set when the accepted application becomes a student. Also set
            // null on delete, so removing a student does not silently delete
            // the application they came from.
            $table->foreignId('enrolled_student_profile_id')
                ->nullable()
                ->constrained('student_profiles')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });

        $driver = DB::connection()->getDriverName();

        // Laravel's schema builder cannot express a CHECK constraint, and
        // SQLite does not support adding one through ALTER TABLE, so it is
        // applied on PostgreSQL (the application database) only.
        if ($driver === 'pgsql') {
            DB::statement(
                "ALTER TABLE admissions ADD CONSTRAINT admissions_status_check CHECK (status IN ('pending', 'reviewed', 'accepted', 'rejected', 'enrolled'))"
            );
        }

        // One live application per number. Laravel's ->unique() cannot
        // express a WHERE clause, so the DDL is written by hand, as every
        // other table in this schema does it. Partial so a withdrawn
        // application frees its number for reuse.
        DB::statement(
            'CREATE UNIQUE INDEX admissions_application_number_unique ON admissions (application_number) WHERE deleted_at IS NULL'
        );

        // The de-duplication check. One person, one live application: this is
        // what stops the same person filling the public form repeatedly, or
        // applying to two batches at once to take the better offer.
        DB::statement(
            'CREATE UNIQUE INDEX admissions_cnic_bform_unique ON admissions (cnic_bform) WHERE deleted_at IS NULL'
        );

        // The admin list is filtered by batch and by stream, and the public
        // form writes through both, so each gets its own index rather than
        // relying on the foreign key constraint to cover the read.
        DB::statement(
            'CREATE INDEX admissions_batch_id_index ON admissions (batch_id)'
        );

        DB::statement(
            'CREATE INDEX admissions_stream_applied_id_index ON admissions (stream_applied_id)'
        );
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admissions');
    }
};
