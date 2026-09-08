<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserRoleCheckConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_role_check_constraint_rejects_an_invalid_raw_insert(): void
    {
        $this->expectException(QueryException::class);

        DB::table('users')->insert([
            'name' => 'Bad Role User',
            'email' => 'bad-role@example.com',
            'password' => 'irrelevant',
            'role' => 'superadmin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_the_role_check_constraint_accepts_the_allowed_values(): void
    {
        foreach (['admin', 'librarian', 'member'] as $role) {
            DB::table('users')->insert([
                'name' => "User {$role}",
                'email' => "{$role}@example.com",
                'password' => 'irrelevant',
                'role' => $role,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->assertSame(3, DB::table('users')->count());
    }
}
