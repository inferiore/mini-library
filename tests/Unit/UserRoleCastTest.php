<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleCastTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_role_casts_to_the_backed_enum_on_round_trip(): void
    {
        // `role` isn't mass-assignable (not in User's #[Fillable] list yet -
        // that's for spec 002's registration flow), so set it directly to
        // prove the cast itself, independent of fillable rules.
        $user = User::factory()->create();
        $user->role = UserRole::Librarian;
        $user->save();

        $fresh = $user->fresh();

        $this->assertInstanceOf(UserRole::class, $fresh->role);
        $this->assertSame(UserRole::Librarian, $fresh->role);
        $this->assertSame('librarian', $fresh->role->value);
    }

    public function test_user_role_defaults_to_member_when_not_set(): void
    {
        $user = User::factory()->create();

        $this->assertSame(UserRole::Member, $user->fresh()->role);
    }
}
