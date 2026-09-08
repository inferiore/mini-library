<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed the 3 fixed demo accounts used by the login page's one-click
     * demo-login panel (docs/specs/002-authentication.md). Safe to re-run.
     */
    public function run(): void
    {
        $accounts = [
            ['name' => 'Demo Admin', 'email' => 'admin@library.test', 'role' => UserRole::Admin],
            ['name' => 'Demo Librarian', 'email' => 'librarian@library.test', 'role' => UserRole::Librarian],
            ['name' => 'Demo Member', 'email' => 'member@library.test', 'role' => UserRole::Member],
        ];

        foreach ($accounts as $account) {
            User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'role' => $account['role'],
                    'password' => Hash::make('password'),
                ],
            );
        }
    }
}
