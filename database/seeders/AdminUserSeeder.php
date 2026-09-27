<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Create the initial admin user from environment variables.
     *
     * Required .env keys:
     *   ADMIN_NAME
     *   ADMIN_EMAIL
     *   ADMIN_PASSWORD
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');
        $name = env('ADMIN_NAME', 'College Administrator');

        if (empty($email) || empty($password)) {
            $this->command->warn(
                'ADMIN_EMAIL or ADMIN_PASSWORD is not set in .env. Skipping admin user creation.'
            );
            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'role' => UserRole::Admin,
                'is_active' => true,
            ]
        );

        $this->command->info("Admin user ensured: {$email}");
    }
}