<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the class_subjects table.
     *
     * One row says "this subject is taught in this class, by this teacher,
     * for this many periods a week". It is the pivot between classes and
     * subjects, carrying the two columns a plain pivot cannot: who teaches
     * the subject and how much weekly contact time it gets. Timetables,
     * attendance and assessments will hang off these rows.
     */
    public function up(): void
    {
        Schema::create('class_subjects', function (Blueprint $table) {
            $table->id();

            // CASCADE: a hard-deleted class takes its assignments with it.
            $table->foreignId('class_id')
                ->index()
                ->constrained('classes')
                ->cascadeOnDelete();

            // RESTRICT on both of these: a subject or a teacher that is still
            // referenced by an assignment must never be removed silently.
            $table->foreignId('subject_id')
                ->index()
                ->constrained('subjects')
                ->restrictOnDelete();

            $table->foreignId('teacher_id')
                ->index()
                ->constrained('users')
                ->restrictOnDelete();

            $table->smallInteger('periods_per_week')->default(5);

            $table->timestamps();
            $table->softDeletes();
        });

        $driver = DB::connection()->getDriverName();

        // Laravel's schema builder cannot express CHECK constraints, and
        // SQLite does not support adding them through ALTER TABLE, so the
        // check is applied on PostgreSQL (the application database) only.
        if ($driver === 'pgsql') {
            DB::statement(
                'ALTER TABLE class_subjects ADD CONSTRAINT class_subjects_periods_per_week_check CHECK (periods_per_week BETWEEN 1 AND 20)'
            );
        }

        // A subject can only be assigned once per class, and only among rows
        // that have not been soft deleted - so a subject that was removed and
        // added back later can be restored instead of duplicated. Both
        // columns are NOT NULL, so a plain partial unique index is enough.
        DB::statement(
            'CREATE UNIQUE INDEX class_subjects_class_subject_unique '
            .'ON class_subjects (class_id, subject_id) '
            .'WHERE deleted_at IS NULL'
        );
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_subjects');
    }
};
