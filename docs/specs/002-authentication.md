# 002 — Authentication

Status: implemented
Area: backend+frontend
Depends on: 001-project-foundation

## Objective

Ship working email/password authentication (register, login, logout, password reset)
via Laravel Fortify + Inertia, with the `role` established in 001 driving authorization
everywhere downstream. Design the auth layer so a Socialite-based SSO provider can be
added later purely as configuration, without restructuring how `role`/authorization
works.

## User Story

As a visitor, I want to register and log in with email/password, so that I can access
the library system as a MEMBER by default, while librarians/admins are provisioned with
elevated roles.

## Functional Requirements

1. A visitor can register with name, email, password (+confirmation) and is logged in
   immediately after, assigned `role = member`.
2. A registered user can log in with email/password and log out.
3. A registered user can request a password reset email and set a new password via a
   signed, expiring link (Laravel's standard `password_resets` flow, driven through
   Fortify).
4. `ADMIN` and `LIBRARIAN` accounts are not self-registerable — they're provisioned via
   a `php artisan` seeder/command (documented, not exposed via public UI). Spec 002
   ships an `artisan users:promote {email} {role}` command as the interim provisioning
   path.
5. Authenticated Inertia pages receive the current user (including `role`) via the
   standard Inertia shared-props mechanism, so React components can branch on role
   without a separate API round-trip.
6. The system is structured so that adding a Socialite driver later only requires: a
   new `SocialiteController`-style route + one config block naming the provider's
   client ID/secret env vars — no change to the `User` model, `role` assignment logic,
   or session handling.
7. **Demo login (MVP-only convenience)**: a `UserSeeder` creates three fixed demo
   accounts, one per role (`admin@library.test` / `librarian@library.test` /
   `member@library.test`, all password `password`). When demo login is enabled (see
   Non-Functional Requirements), the `/login` page shows a "Demo accounts" panel with
   one button per seeded user — clicking a button logs in as that user immediately, no
   typing required, so a reviewer/interviewer can explore every role without knowing or
   entering credentials.

## Demo Login — Non-Functional Requirements (safety gate)

- Demo login is gated by a dedicated `DEMO_LOGIN_ENABLED` env flag (`config('auth.demo_login_enabled')`),
  **not** simply `app()->environment('local')` — an explicit flag is harder to leave
  accidentally on than an environment-name check, and it's easy to confirm at a glance
  in `.env`/`.env.example`. Defaults to `true` locally, and `DEPLOYMENT.md` (spec 010)
  must call out that it should be `false` (or the route simply unregistered) for any
  deployment meant to be shown to real, untrusted users.
- The demo-login endpoint never accepts a password — it looks up the seeded user by a
  fixed, allow-listed identifier (e.g. `role` name, not a raw email/id from the client)
  and logs in directly via `Auth::login()`. It must reject any request when
  `DEMO_LOGIN_ENABLED` is false, returning 404 (not just hiding the button) so the
  route genuinely isn't usable, not just unlinked.
- This is explicitly an MVP/demo affordance, not a real "impersonation" feature — it
  does not require an already-authenticated admin, and must never ship enabled by
  default in a spec 010 production deployment.

## Non-Functional Requirements

- No SSO client ID/secret is configured or hardcoded in this spec — Socialite isn't
  installed yet, only the seam for it is left clean (a `SocialAccount`-shaped
  extension point is _not_ built prematurely; this spec just avoids doing anything that
  would need undoing later, e.g. don't assume email+password is the only possible
  identity source when writing the registration→role-assignment logic).
- Passwords are hashed via Laravel's default bcrypt; `BCRYPT_ROUNDS` stays
  environment-configurable (already the case in the scaffold).
- Session-based auth (not token/API auth) — this is a server-rendered Inertia app, not
  a decoupled SPA consuming a public API.

## User Flow

1. Visitor lands on `/register`, submits name/email/password → redirected to
   `/dashboard` as an authenticated MEMBER.
2. Returning user visits `/login`, submits credentials → redirected to `/dashboard`.
3. User clicks "Forgot password" → enters email → receives reset link (via `MAIL_MAILER`,
   `log` driver locally) → sets new password → redirected to `/login`.
4. Authenticated user clicks "Log out" → session invalidated → redirected to `/`.

## Database Changes

- No new tables beyond Fortify's own conventions (`password_reset_tokens`,
  `sessions` — both are part of Laravel's default migrations, already present in the
  scaffold). `role` already added in 001.

## API/Application Changes

- Install `laravel/fortify`, configure via `config/fortify.php` with Inertia views
  (`Fortify::loginView`, etc. rendering Inertia pages, per Fortify's Inertia recipe).
- `FortifyServiceProvider` (or `AppServiceProvider`) wires `Fortify::createUsersUsing`
  to a `CreateNewUser` action that sets `role = UserRole::Member` explicitly (never
  trust a client-supplied role field).
- `app/Console/Commands/PromoteUserRole.php` — `users:promote {email} {role}`, validates
  the role against `UserRole`, updates the user, errors clearly if the email doesn't
  exist or the role is invalid.
- Route middleware: `auth` for all protected routes (dashboard, books management,
  loans, admin), applied per-route/group in later specs — 002 only establishes the
  middleware and the guest/auth redirect behavior.
- Inertia shared data (`HandleInertiaRequests::share()`): `auth.user` including `id`,
  `name`, `email`, `role`.
- `database/seeders/UserSeeder.php`: creates the 3 fixed demo accounts (idempotent —
  `firstOrCreate` by email, safe to re-run), called from `DatabaseSeeder`.
- `App\Http\Controllers\DemoLoginController@store` (`POST /demo-login/{role}`, `role`
  constrained to `admin|librarian|member` via route enum/regex): 404s immediately if
  `!config('auth.demo_login_enabled')`; otherwise looks up the matching seeded demo
  user by role and calls `Auth::login($user)`.
- `config/auth.php` (or a new `config/demo.php`): `demo_login_enabled` from
  `env('DEMO_LOGIN_ENABLED', true)`.

## UI Changes

- `resources/js/pages/auth/login.tsx`, `register.tsx`, `forgot-password.tsx`,
  `reset-password.tsx` — Tailwind-styled forms using Inertia's `useForm`, client-side
  field errors surfaced from Laravel validation.
- A minimal authenticated shell/layout (nav with user name + role badge + logout)
  reused by all authenticated pages built in later specs.
- `login.tsx` conditionally renders a "Demo accounts" panel (three buttons: "Continue
  as Admin/Librarian/Member") when the page receives `demoLoginEnabled: true` as an
  Inertia prop (server-controlled, not a client-side env check) — each button posts to
  `/demo-login/{role}` and follows the redirect.

## Authorization

- Registration is public; login/logout/password-reset are public (pre-auth) routes.
- All other routes require `auth` middleware (enforced per-route as those routes are
  built in 003+).
- Only `ADMIN`/`LIBRARIAN` provisioning happens out-of-band via the artisan command —
  no UI path grants elevated roles in this spec.

## Validation Rules

- Registration: `name` required string; `email` required, valid email, unique on
  `users`; `password` required, confirmed, Laravel's default password rules (min
  length via `Password::defaults()`).
- Login: `email` required valid email, `password` required — generic "these
  credentials do not match" error on failure (no user-enumeration via distinct error
  messages).
- `users:promote` command: role argument must match a `UserRole` case exactly
  (case-insensitive accepted, normalized), email must exist.

## Edge Cases

- Registering with an already-used email returns a field-level validation error, not a
  generic failure.
- Requesting a password reset for a non-existent email returns the same success
  response as a real one (no enumeration via timing/response difference).
- An expired or already-used password reset link shows a clear error and lets the user
  request a new one.
- Logging out invalidates the session server-side (not just a client-side redirect) —
  the back button after logout must not show authenticated content.

## Acceptance Criteria

- A new visitor can complete registration and land on an authenticated page as a
  MEMBER.
- A registered user can log in, log out, and the logged-out session can't access
  protected routes (redirects to `/login`).
- Password reset end-to-end works using the `log` mail driver in local/testing.
- `php artisan users:promote someone@example.com librarian` changes that user's role
  and takes effect on their next request (role is read fresh, not cached in the
  session).
- No client-supplied `role` field can influence the role assigned at registration.
- Clicking each of the three demo-login buttons logs in as that seeded role
  immediately, with no password entry.
- With `DEMO_LOGIN_ENABLED=false`, the demo panel doesn't render and the
  `/demo-login/{role}` route itself 404s even if hit directly.

## Test Cases

1. Feature test: registration creates a `member`-role user and logs them in.
2. Feature test: registration rejects a duplicate email with a validation error.
3. Feature test: login with correct/incorrect credentials.
4. Feature test: logout invalidates the session (subsequent protected request redirects
   to login).
5. Feature test: password reset flow (request → token → reset → login with new
   password).
6. Feature test: an unauthenticated request to a protected route redirects to `/login`.
7. Unit/console test: `users:promote` updates role for a valid email+role, errors for
   invalid email or invalid role string.
8. Feature test: submitting `role=admin` in the registration payload is ignored — the
   created user is still `member`.
9. Feature test: `POST /demo-login/admin` (and librarian/member) logs in as the correct
   seeded user when `DEMO_LOGIN_ENABLED=true`.
10. Feature test: `POST /demo-login/admin` returns 404 when `DEMO_LOGIN_ENABLED=false`.
11. Feature test: `UserSeeder` run twice doesn't error or duplicate the demo accounts.

## Definition of Done

- Migrations run cleanly on both the fast local SQLite suite and (where Postgres-only
  behavior is involved) `phpunit.ci.xml`.
- All Acceptance Criteria met.
- All Test Cases covered by passing automated tests.
- `composer test` passes (pint, phpstan, PHPUnit).
- No hardcoded secrets introduced.
- `qa-validator` has returned `STATUS: PASSED`.
