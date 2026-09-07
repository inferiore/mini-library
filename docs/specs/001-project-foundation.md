# 001 — Project Foundation

Status: draft
Area: infra+backend
Depends on: none

## Objective

Establish the runnable skeleton every later spec builds on: Docker Compose for local
and prod, PostgreSQL+pgvector, the database queue driver, base migrations (roles on
`users`), CI, and the AI provider abstraction scaffolding (interfaces + fakes, no real
feature wired to them yet). No product feature (books, loans, auth UI) ships in this
spec — it's the ground everything else stands on.

## User Story

As a developer on this project, I want a one-command local environment and a CI
pipeline that actually exercises Postgres/pgvector behavior, so that every subsequent
spec can be implemented and validated against real infrastructure instead of
assumptions.

## Functional Requirements

1. `docker compose up -d` starts `app`, `web`, `postgres`, `queue`, and (locally only)
   `vite`, resulting in a reachable Laravel app at the configured local URL.
2. `postgres` runs `pgvector/pgvector:pg16` with the `vector` extension available
   (`CREATE EXTENSION IF NOT EXISTS vector` run via migration).
3. `queue` runs `php artisan queue:work` (prod) / `queue:listen` (dev, auto-reloads job
   class changes) against `QUEUE_CONNECTION=database`.
4. Editing a PHP/Blade/Inertia file under bind-mounted source is visible without an
   image rebuild or container recreation. Editing JS/CSS is visible via Vite HMR.
5. Editing a `.env` value takes effect after `docker compose up -d --force-recreate`,
   never `docker compose build`.
6. `users` table gains a `role` column (`admin`/`librarian`/`member`), backed by a PHP
   enum (`App\Enums\UserRole`), with a DB-level CHECK constraint restricting values —
   portable to both SQLite (local PHPUnit) and Postgres.
7. `app/AI/Contracts/EmbeddingServiceInterface.php` and `LLMServiceInterface.php` exist,
   with `App\AI\Http\OpenAiCompatibleEmbeddingService` /
   `OpenAiCompatibleLLMService` as the default bindings (configured for Gemini's
   OpenAI-compatible endpoint via `config/ai.php`) and
   `App\AI\Fakes\FakeEmbeddingService` / `FakeLLMService` for tests. No feature calls
   these yet — this spec only proves the binding + fake-swap machinery works via a
   throwaway smoke test, which is then deleted once spec 007/008 use it for real.
8. GitHub Actions runs on every PR: `static-analysis` (pint + phpstan), `frontend`
   (`npm run check`, `types:check`, `build`), `backend-tests` (PHPUnit against the fast
   SQLite suite *and* against a Postgres+pgvector service container via
   `phpunit.ci.xml`), `docker-build` (builds the `production` Dockerfile target).

## Non-Functional Requirements

- No secret (API key, DB password, `APP_KEY`) is ever baked into a Docker image layer
  or committed file. `.env` stays gitignored; `.env.example` lists key names only.
- The Postgres-only migration statements (extension creation, any future
  `vector`/HNSW DDL) must no-op or be skipped cleanly when the active connection is
  `sqlite`, so the fast local test suite keeps working without a real Postgres.
- Image build time shouldn't regress local iteration speed — dependency layers
  (`composer install`, `npm ci`) must be cached appropriately in the Dockerfile.

## User Flow

Not user-facing — this is pure infrastructure. The "flow" is developer-facing:
`git clone` → `cp .env.example .env` (fill in secrets locally) → `docker compose up -d`
→ app is reachable, migrations have run, queue worker is up, Vite HMR is live.

## Database Changes

- Migration: add `role` (string, default `member`) to `users`, plus a raw-SQL CHECK
  constraint `role IN ('admin','librarian','member')`.
- Migration: `CREATE EXTENSION IF NOT EXISTS vector` guarded to `pgsql` only (no-op on
  `sqlite`) — this is preparatory for spec 007, not used yet.
- No other domain tables in this spec (books/loans/rag_documents land in their own
  specs).

## API/Application Changes

- `App\Enums\UserRole` (backed string enum: `Admin='admin'`, `Librarian='librarian'`,
  `Member='member'`), cast on `User::$casts`.
- `app/AI/Contracts/EmbeddingServiceInterface.php`,
  `app/AI/Contracts/LLMServiceInterface.php`.
- `app/AI/Http/OpenAiCompatibleEmbeddingService.php`,
  `app/AI/Http/OpenAiCompatibleLLMService.php` — generic HTTP client using Laravel's
  `Http` facade, configured entirely from `config/ai.php` (`base_url`, `api_key`,
  `chat_model`, `embedding_model`) so a real OpenAI endpoint or any other
  OpenAI-compatible provider works by changing config, not code.
- `app/AI/Fakes/FakeEmbeddingService.php` (deterministic vector from an input hash),
  `app/AI/Fakes/FakeLLMService.php` (deterministic templated string).
- `AppServiceProvider` binds both interfaces to the `OpenAiCompatible*` classes.
  `Tests\TestCase::setUp()` rebinds both to the fakes.
- `config/ai.php`: `base_url`, `api_key`, `chat_model`, `embedding_model`,
  `embedding_dimensions` — all sourced from env (`LLM_BASE_URL`, `LLM_API_KEY`,
  `CHAT_MODEL`, `EMBEDDING_MODEL`, `VECTOR_DIM`).
- `docker-entrypoint.sh`: runs `php artisan config:cache && php artisan migrate --force`
  at container start, then execs the container's actual process (php-fpm or
  queue:work), so `.env` changes never require an image rebuild.
- `vite.config.ts`: add an explicit `server.host`/`hmr.host` block for Docker-network
  HMR (this project isn't using Sail's auto-detection).

## UI Changes

None in this spec.

## Authorization

None yet — no protected routes exist in this spec (auth lands in 002). The `role`
column and `UserRole` enum exist so 002+ can build Policies against them immediately.

## Validation Rules

- `role` must be one of the three enum values at the DB layer (CHECK constraint) as
  defense-in-depth beneath whatever application-level validation 002 adds at
  registration.

## Edge Cases

- Running the suite with `DB_CONNECTION=sqlite` (default local/PHPUnit) must not fail
  on the `CREATE EXTENSION vector` migration — it must be skipped, not silently
  swallowed-and-ignored in a way that masks a real Postgres failure in CI.
- A container recreation after an `.env` change must not require re-running
  `composer install`/`npm ci` (those live in the image layer, not entrypoint).
- If `LLM_API_KEY` is unset locally, the app must still boot and all non-AI features
  must work — the smoke test for the AI bindings uses the fakes, not a live call.

## Acceptance Criteria

- `docker compose up -d` from a clean clone (with a filled-in `.env`) results in a
  reachable app, applied migrations, and a running queue worker.
- Editing a PHP file and refreshing the browser reflects the change with no rebuild.
- Editing a Tailwind/CSS class reflects via HMR without a hard refresh being required
  (though hard refresh must also work normally).
- `docker compose up -d --force-recreate` after an `.env` edit picks up the new value
  without an image rebuild.
- CI's `backend-tests` job passes against both the SQLite fast suite and
  `phpunit.ci.xml` (Postgres+pgvector service container).
- `php artisan tinker` resolving `EmbeddingServiceInterface`/`LLMServiceInterface`
  returns the `OpenAiCompatible*` implementations outside tests, and the fakes inside
  `Tests\TestCase`-based tests.
- No `.env` value, API key, or DB password appears in any committed file or Docker
  image layer (verified by inspecting `git diff` and the built image's filesystem).

## Test Cases

1. Feature test: booting the app and running a request resolves
   `EmbeddingServiceInterface` to the fake in the test environment.
2. Unit test: `UserRole` enum casts correctly on a `User` model round-trip.
3. Migration test: the `role` CHECK constraint rejects an invalid raw insert (both
   SQLite and, via `phpunit.ci.xml`, Postgres).
4. CI smoke: `backend-tests` job green against the Postgres service container,
   confirming `CREATE EXTENSION vector` succeeded there.

## Definition of Done

- Migrations run cleanly on both the fast local SQLite suite and `phpunit.ci.xml`.
- All Acceptance Criteria met.
- All Test Cases covered by passing automated tests.
- `composer test` passes (pint, phpstan, PHPUnit).
- No hardcoded secrets introduced.
- `qa-validator` has returned `STATUS: PASSED`.
