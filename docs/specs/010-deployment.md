# 010 — Deployment

Status: approved
Area: infra
Depends on: 001-project-foundation (Docker Compose skeleton, CI shape)

## Re-scoping note (2026-09-08)

This spec originally deferred automated deployment (CD) to a live host until the
application was feature-complete and a real deploy target existed — see the
"Objective," `## Functional Requirements` item 2, `needed_variable.md`'s former
"Deferred" section, and the last bullet of the original "Definition of Done" below,
which explicitly named a follow-up spec (`011-live-deployment.md`) as the expected
path.

Per explicit user direction on 2026-09-08 — the application is now feature-complete
(specs 001–009 implemented) and a real deploy target/credentials process is in place —
that follow-up was folded into this spec directly instead of being split out. **Only
this scope decision changed; the rest of this spec's content below is left as
originally written** (it remains accurate — build+validate CI was already implemented
under specs 001/002, and everything below still describes it correctly). What's
different in practice:

- Automated CD **is now implemented**: a `deploy` job in
  `.github/workflows/tests.yml`, gated to `main`-only pushes and to all prior jobs
  (`static-analysis`, `frontend`, `backend-tests`, `docker-build`) passing, deploys over
  SSH using `appleboy/ssh-action` and four new GitHub Secrets (`SSH_HOST`, `SSH_USER`,
  `DEPLOY_SSH_KEY`, `DEPLOY_PATH`). See `DEPLOYMENT.md`'s "Automated Deployment (CD)"
  section for exactly what it does and doesn't do — it's intentionally honest about
  real remaining gaps (no rollback-on-failure, no image registry, no zero-downtime
  deploy, no DB backup-before-migrate; full list in `DEPLOYMENT.md`'s "Not Yet
  Implemented" section).
- Still no image registry — deploy and rollback both work by rebuilding the production
  image from source on the target host itself, not by pulling a pre-built tag. This
  remains a real, undone gap (see `DEPLOYMENT.md`), not silently solved by adding CD.
- `needed_variable.md` has been updated to move the SSH/deploy-path secrets out of its
  "Deferred" section and list them as required GitHub Secrets for this now-implemented
  `deploy` job.

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
   required GitHub Secrets _for when CD is added later_, DB/pgvector setup steps,
   queue worker operation, the build process, a **manual** deploy runbook (how someone
   would deploy today, by hand, using the production Docker Compose files from spec 001
   — `docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d`), a
   rollback approach (keep the previous image tag, `docker compose ... up -d` back to
   it), and basic health checks (`GET /up`, Laravel's built-in health-check route, plus
   confirming the queue worker container is running).
4. ~~A section explicitly titled "Not Yet Implemented: Automated Deployment" states
   what a future spec would need to add automated CD (a target host or platform, a
   registry, deploy credentials as GitHub Secrets) — so this gap is documented as a
   decision, not a silent omission.~~ **Superseded by the 2026-09-08 re-scoping**: CD is
   now implemented (a `deploy` job using SSH + `DEPLOY_PATH`/`DEPLOY_SSH_KEY`/
   `SSH_HOST`/`SSH_USER` secrets — see `DEPLOYMENT.md`'s "Automated Deployment (CD)"
   section). `DEPLOYMENT.md` still has a "Not Yet Implemented" section, but it now
   lists the real remaining gaps in the _implemented_ pipeline (no rollback-on-failure,
   no registry, no zero-downtime deploy, no DB backup-before-migrate, no host
   provisioning) rather than describing CD itself as unbuilt.

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
- ~~The "Not Yet Implemented" section clearly states automated CD is deferred and what
  it would require.~~ **Superseded 2026-09-08**: automated CD is now implemented (see
  the "Re-scoping note" above); `DEPLOYMENT.md`'s "Not Yet Implemented" section instead
  honestly lists the real gaps remaining _within_ that implemented pipeline (no
  rollback-on-failure, no registry, no zero-downtime deploy, no DB
  backup-before-migrate, no host provisioning, no DNS/TLS, no secret-rotation
  automation).

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
  **Done, in-place, on 2026-09-08** — see the "Re-scoping note" at the top of this
  spec; folded into this spec directly rather than a separate `011`.
