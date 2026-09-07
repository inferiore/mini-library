# 010 — Deployment

Status: approved
Area: infra
Depends on: 001-project-foundation (Docker Compose skeleton, CI shape)

## Objective

Make the application's build-and-validate pipeline production-grade and fully
documented, without yet wiring automated deployment to a live host — per the user's
explicit decision to defer live-deployment specifics until the rest of the app is
built and there's a real target to deploy to. This spec's scope is intentionally
narrower than the master requirements doc's full "GitHub → Actions → build → deploy →
production" pipeline: it covers everything up to "produces a correct, runnable,
secret-free Docker image," and documents (without implementing) what a future deploy
step would need.

## User Story

As the developer, I want CI to prove that every merge produces a working, tested,
correctly-built production Docker image, and a DEPLOYMENT.md that tells me (or a future
collaborator) exactly what's needed to actually run that image somewhere real, so that
deploying later is a documented decision, not a scramble.

## Functional Requirements

1. On every PR and on `main`, GitHub Actions runs (building on spec 001's job
   skeleton): `static-analysis`, `frontend`, `backend-tests` (SQLite + Postgres/
   `phpunit.ci.xml`), and `docker-build` — building the `production` Dockerfile target
   and failing the workflow if the image doesn't build cleanly.
2. `docker-build` does **not** push the image to any registry in this spec (no GHCR/
   Docker Hub credentials are configured) — it validates the build only. Pushing is
   explicitly out of scope until a follow-up spec names a real registry/target.
3. `DEPLOYMENT.md` documents: required infrastructure (what services must exist:
   Postgres+pgvector, a queue worker process, the app itself), the full list of
   required environment variables with their purpose (never example real values),
   required GitHub Secrets *for when CD is added later*, DB/pgvector setup steps,
   queue worker operation, the build process, a **manual** deploy runbook (how someone
   would deploy today, by hand, using the production Docker Compose files from spec 001
   — `docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d`), a
   rollback approach (keep the previous image tag, `docker compose ... up -d` back to
   it), and basic health checks (`GET /up`, Laravel's built-in health-check route, plus
   confirming the queue worker container is running).
4. A section explicitly titled "Not Yet Implemented: Automated Deployment" states what
   a future spec would need to add automated CD (a target host or platform, a registry,
   deploy credentials as GitHub Secrets) — so this gap is documented as a decision, not
   a silent omission.

## Non-Functional Requirements

- Nothing in this spec requires any production credential to exist yet — CI runs
  entirely against ephemeral service containers (Postgres) and fake AI providers,
  never real external services.
- The `production` Dockerfile target must not embed `.env`, `APP_KEY`, or any secret —
  verified by a CI step (or a documented manual check) that inspects the built image's
  layers/filesystem for stray secret files.

## User Flow

Not user-facing — developer/operator-facing only. A future operator reading
`DEPLOYMENT.md` should be able to stand up the stack manually on any Docker-capable
host using the Compose files this project already has, without needing to reverse-
engineer anything from the codebase.

## Database Changes

None.

## API/Application Changes

- `.github/workflows/*.yml`: extend spec 001's CI skeleton with the `docker-build` job
  (build-only, `needs: [static-analysis, frontend, backend-tests]`).
- No application code changes in this spec.

## UI Changes

None.

## Authorization

N/A — infra/CI only, no application routes.

## Validation Rules

N/A.

## Edge Cases

- A PR that only touches documentation still runs the full CI suite (no path-based
  skip in this spec — keep CI simple; path filtering can be added later if build times
  become a real problem, but isn't a documented requirement here).
- A Docker build failure (e.g. a broken Composer/npm lockfile) fails `docker-build`
  clearly, distinct from a test failure, so it's obvious which stage broke.

## Acceptance Criteria

- CI's `docker-build` job builds the `production` Dockerfile target successfully on a
  clean `main` and fails clearly on a broken build.
- `DEPLOYMENT.md` exists and accurately documents required env vars, infra, manual
  deploy steps using the existing Compose files, rollback, and health checks.
- No secret is baked into the built image (spot-checked).
- The "Not Yet Implemented" section clearly states automated CD is deferred and what
  it would require.

## Test Cases

1. CI run: `docker-build` succeeds against a known-good `main`.
2. CI run: `docker-build` fails clearly when a deliberately broken dependency file is
   introduced (manual verification during implementation, not a permanent test left in
   the suite).
3. Manual check: `docker history`/`docker run --rm <image> env` on the built image
   shows no `LLM_API_KEY`, `APP_KEY`, or DB credential baked in.
4. Manual check: following `DEPLOYMENT.md`'s manual deploy runbook on a local Docker
   host (or the existing dev Compose stack standing in for "production-shaped")
   successfully brings up app + postgres + queue and passes the health check.

## Definition of Done

- All Acceptance Criteria met.
- `composer test` and CI both pass on `main`.
- `DEPLOYMENT.md` is accurate as of this spec's implementation (no aspirational/
  unimplemented steps presented as if they work today).
- No hardcoded secrets introduced anywhere in this spec's changes.
- `qa-validator` has returned `STATUS: PASSED`.
- Revisit and re-scope this spec (or open a follow-up, e.g. `011-live-deployment.md`)
  once the application is otherwise feature-complete and a real deploy target exists,
  per the user's explicit request to review deployment specifics at that point.
