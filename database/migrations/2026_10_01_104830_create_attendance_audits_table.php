<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the attendance_audits table.
     *
     * One row is one correction somebody made to one mark: "this record was
     * absent, an admin changed it to present on 3 October because the register
     * was mislaid, and here is why."
     *
     * The audit trail is what makes an attendance record defensible. A mark a
     * teacher cannot change after submitting is only trustworthy if there is
     * somewhere to record the fact that it was changed anyway - and that
     * somewhere has to be outside the record it describes, or an admin could
     * quietly rewrite history by deleting the correction.
     *
     * Deliberately no softDeletes. An audit row is never withdrawn; a
     * mistaken correction is itself corrected, and the mistake stays on file.
     */
    public function up(): void
    {
        Schema::create('attendance_audits', function (Blueprint $table) {
            $table->id();

            // CASCADE, unlike every other foreign key in the attendance
            // schema. An audit row has no meaning without the record it
            // describes, and there is no case for keeping an orphaned
            // correction: if the attendance row is ever hard-deleted, so is
            // the evidence about it.
            $table->foreignId('attendance_id')
                ->constrained('attendances')
                ->cascadeOnDelete();

            // RESTRICT, unlike the one above. The whole point of the audit is
            // to name the person who made the change, so the admin account must
            // not be deletable while a correction still credits them.
            $table->foreignId('edited_by')
                ->constrained('users')
                ->restrictOnDelete();

            // Both statuses are plain strings rather than constrained to the
            // four live marks, because one of them is 'deleted' - the
            // terminal state of a removed record. Constraining this column to
            // Attendance::STATUSES would make deletions unauditable.
            $table->string('old_status', 10);

            $table->string('new_status', 10);

            // Not nullable, unlike on the admission rejection: an edit with no
            // stated reason is exactly the sort of change an audit exists to
            // explain.
            $table->string('reason', 255);

            $table->timestamps();
        });

        /*
            Three indexes, each for one way the trail is read.

            attendance_id is the "what happened to this record" question, and is
            the foreign key's own index on most drivers anyway. edited_by is
            "what has this admin been changing", which is the audit question an
            investigating officer actually asks. created_at is the third, because
            "everything changed in the last week" is how a trail is read
            chronologically when something looks wrong.

            Timestamps are indexed separately rather than descending on the pair:
            PostgreSQL can scan a btree backwards, so an explicit DESC would
            store the same keys in the same order for no benefit.
        */
        Schema::table('attendance_audits', function (Blueprint $table): void {
            $table->index('attendance_id');
            $table->index('edited_by');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_audits');
    }
};
