<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * The first dashboard login. The password is deliberately weak and obvious in
 * local development and must be replaced on the server — the deployment
 * runbook says so, and this seeder is not run in production.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@kcs.edu.za'],
            [
                'name' => 'KCS Administrator',
                'password' => Hash::make('password'),
                'role' => UserRole::ADMIN,
            ],
        );
    }
}
