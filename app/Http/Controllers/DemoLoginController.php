<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DemoLoginController extends Controller
{
    /**
     * Log in as a fixed seeded demo user for the given role, bypassing
     * password entry entirely. MVP/demo convenience only — see
     * docs/specs/002-authentication.md. Never accepts a password or a
     * client-supplied identifier beyond the role name itself.
     */
    public function store(string $role): RedirectResponse
    {
        if (! config('auth.demo_login_enabled')) {
            throw new NotFoundHttpException;
        }

        $userRole = UserRole::tryFrom($role);

        if ($userRole === null) {
            throw new NotFoundHttpException;
        }

        $user = User::where('role', $userRole)->firstOrFail();

        Auth::login($user);

        return redirect()->intended(config('fortify.home'));
    }
}
