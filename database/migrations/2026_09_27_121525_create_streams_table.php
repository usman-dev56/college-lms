<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the streams table.
     *
     * A stream is an academic track that Grade 11 and Grade 12 students
     * choose, e.g., Pre-Medical, Pre-Engineering, ICS, Commerce, Humanities.
     * Subjects (sub-stage 2.3) will reference a stream.
     */
    public function up(): void
    {
        Schema::create('streams', function (Blueprint $table) {
            $table->id();

            $table->string('name', 50);
            $table->string('code', 10);
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();
            $table->softDeletes();
        });

        // Partial unique indexes: a name/code only has to be unique among
        // rows that have not been soft deleted, so a deleted stream does
        // not block re-creating it later. Laravel's ->unique() cannot
        // express a WHERE clause, so the DDL is written by hand.
        DB::statement(
            'CREATE UNIQUE INDEX streams_name_unique ON streams (name) WHERE deleted_at IS NULL'
        );

        DB::statement(
            'CREATE UNIQUE INDEX streams_code_unique ON streams (code) WHERE deleted_at IS NULL'
        );
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('streams');
    }
};
