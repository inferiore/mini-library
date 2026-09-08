<?php

namespace Tests\Feature\Auth;

use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_login_logs_in_as_the_correct_seeded_user_per_role(): void
    {
        $this->seed(UserSeeder::class);

        config(['auth.demo_login_enabled' => true]);

        foreach (['admin', 'librarian', 'member'] as $role) {
            $response = $this->post("/demo-login/{$role}");

            $this->assertAuthenticated();
            $this->assertSame($role, auth()->user()->role->value);
            $response->assertRedirect('/dashboard');

            auth()->logout();
        }
    }

    public function test_demo_login_404s_when_disabled(): void
    {
        $this->seed(UserSeeder::class);

        config(['auth.demo_login_enabled' => false]);

        $response = $this->post('/demo-login/admin');

        $response->assertNotFound();
        $this->assertGuest();
    }

    public function test_demo_login_404s_for_an_unknown_role(): void
    {
        config(['auth.demo_login_enabled' => true]);

        $response = $this->post('/demo-login/superadmin');

        $response->assertNotFound();
    }
}
