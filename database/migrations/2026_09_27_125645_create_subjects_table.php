<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the subjects table.
     *
     * A subject is a course taught in one grade (11 or 12). Compulsory
     * subjects (English, Urdu, Islamiat, Pakistan Studies) have no stream
     * and are taken by every student. Elective subjects belong to the
     * stream that offers them and may also be shared with other streams
     * through the stream_subject pivot table.
     */
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();

            $table->string('name', 100);
            $table->string('code', 20)->nullable();
            $table->smallInteger('grade_level');
            $table->foreignId('stream_id')->nullable()->index()->constrained('streams')->nullOnDelete();
            $table->boolean('has_practical')->default(false);
            $table->boolean('is_active')->default(true)->index();

            $table->index('grade_level');

            $table->timestamps();
            $table->softDeletes();
        });

        $driver = DB::connection()->getDriverName();

        // Only the two intermediate years are valid grade levels. Laravel's
        // schema builder cannot express a CHECK constraint, and SQLite does
        // not support adding one through ALTER TABLE, so it is applied on
        // PostgreSQL (the application database) only.
        if ($driver === 'pgsql') {
            DB::statement(
                'ALTER TABLE subjects ADD CONSTRAINT subjects_grade_level_check CHECK (grade_level IN (11, 12))'
            );
        }

        // A subject name may only repeat once per grade level and stream,
        // and only among rows that have not been soft deleted. PostgreSQL
        // needs NULLS NOT DISTINCT here, because a plain unique index treats
        // every NULL as distinct and would let duplicate compulsory subjects
        // (stream_id IS NULL) slip through.
        $nullsClause = $driver === 'pgsql' ? ' NULLS NOT DISTINCT' : '';

        DB::statement(
            'CREATE UNIQUE INDEX subjects_name_grade_level_stream_unique '
            .'ON subjects (name, grade_level, stream_id)'.$nullsClause.' '
            .'WHERE deleted_at IS NULL'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
