<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the stream_subject pivot table.
     *
     * An elective subject can be offered by more than one stream (Physics
     * is offered by Pre-Medical, Pre-Engineering and ICS), so the link
     * between streams and subjects is many-to-many.
     */
    public function up(): void
    {
        Schema::create('stream_subject', function (Blueprint $table) {
            $table->id();

            $table->foreignId('stream_id')->constrained('streams')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['stream_id', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stream_subject');
    }
};
