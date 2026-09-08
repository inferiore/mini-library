<?php

namespace Tests\Feature\Console;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromoteUserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_promotes_an_existing_user_to_a_valid_role(): void
    {
        $user = User::factory()->create();

        $this->artisan('users:promote', ['email' => $user->email, 'role' => 'librarian'])
            ->assertExitCode(0);

        $this->assertSame(UserRole::Librarian, $user->fresh()->role);
    }

    public function test_it_accepts_role_input_case_insensitively(): void
    {
        $user = User::factory()->create();

        $this->artisan('users:promote', ['email' => $user->email, 'role' => 'ADMIN'])
            ->assertExitCode(0);

        $this->assertSame(UserRole::Admin, $user->fresh()->role);
    }

    public function test_it_fails_for_an_unknown_email(): void
    {
        $this->artisan('users:promote', ['email' => 'nobody@example.com', 'role' => 'admin'])
            ->assertExitCode(1);
    }

    public function test_it_fails_for_an_invalid_role(): void
    {
        $user = User::factory()->create();

        $this->artisan('users:promote', ['email' => $user->email, 'role' => 'superadmin'])
            ->assertExitCode(1);

        $this->assertSame(UserRole::Member, $user->fresh()->role);
    }
}
