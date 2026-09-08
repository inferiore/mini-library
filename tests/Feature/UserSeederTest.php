<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_one_demo_user_per_role(): void
    {
        $this->seed(UserSeeder::class);

        $this->assertSame(UserRole::Admin, User::where('email', 'admin@library.test')->firstOrFail()->role);
        $this->assertSame(UserRole::Librarian, User::where('email', 'librarian@library.test')->firstOrFail()->role);
        $this->assertSame(UserRole::Member, User::where('email', 'member@library.test')->firstOrFail()->role);
    }

    public function test_it_is_safe_to_run_twice(): void
    {
        $this->seed(UserSeeder::class);
        $this->seed(UserSeeder::class);

        $this->assertSame(3, User::whereIn('email', [
            'admin@library.test',
            'librarian@library.test',
            'member@library.test',
        ])->count());
    }
}
