<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_can_register_and_is_assigned_the_member_role(): void
    {
        $response = $this->post('/register', [
            'name' => 'New User',
            'email' => 'new-user@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();

        $user = User::where('email', 'new-user@example.com')->firstOrFail();
        $this->assertSame(UserRole::Member, $user->role);

        $response->assertRedirect('/dashboard');
    }

    public function test_registration_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->post('/register', [
            'name' => 'New User',
            'email' => 'taken@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_a_client_supplied_role_field_is_ignored_on_registration(): void
    {
        $this->post('/register', [
            'name' => 'New User',
            'email' => 'no-privilege-escalation@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'admin',
        ]);

        $user = User::where('email', 'no-privilege-escalation@example.com')->firstOrFail();
        $this->assertSame(UserRole::Member, $user->role);
    }
}
