<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add role and profile fields to the users table.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)
                ->default(UserRole::Student->value)
                ->after('email')
                ->index();

            $table->string('phone', 20)
                ->nullable()
                ->unique()
                ->after('role');

            $table->boolean('is_active')
                ->default(true)
                ->after('phone');

            $table->softDeletes();
        });
    }

    /**
     * Reverse the changes.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'phone', 'is_active', 'deleted_at']);
        });
    }
};