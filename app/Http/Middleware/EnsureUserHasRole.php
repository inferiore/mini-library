<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level role gate (spec 009). Registered as the `role` alias so admin-only
 * sections can be grouped declaratively — e.g. `->middleware('role:admin')` —
 * as a reusable complement to the per-action Policy checks. Accepts one or more
 * roles: `role:admin,librarian`.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null || ! in_array($user->role->value, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
