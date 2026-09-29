<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * admissions_open is the switch the public application form reads, and it
     * is deliberately separate from is_active. A batch can be an active
     * year group for years while admissions to it are closed - the running
     * Grade 12 cohort is active but stopped accepting applications the day
     * it filled. Conflating the two would mean reopening admissions every
     * August whether or not anyone decided to.
     *
     * The column is indexed because the public form lists open batches on
     * every visit, and that list is the only query that touches it.
     */
    public function up(): void
    {
        Schema::table('student_batches', function (Blueprint $table) {
            $table->boolean('admissions_open')
                ->default(false)
                ->after('is_active')
                ->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_batches', function (Blueprint $table) {
            $table->dropColumn('admissions_open');
        });
    }
};
