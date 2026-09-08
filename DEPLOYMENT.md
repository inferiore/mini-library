# Deployment

This document is the operator-facing companion to `docs/specs/010-deployment.md`. It
covers what's actually implemented today: required infrastructure, environment
variables, database/pgvector setup, queue worker operation, the build process, the
manual deploy runbook, the automated CD pipeline, rollback, and health checks.

Nothing here requires a production credential to read or to do local development —
this file documents what a real deployment needs, it doesn't supply it.

## Required Infrastructure

A deployment (local-dev-shaped or production-shaped) needs four things running:

1. **The app** (PHP-FPM, `app` service) — serves the Laravel/Inertia application.
   Fronted by **nginx** (`web` service), which proxies PHP requests to `app:9000` over
   FastCGI and serves static assets directly.
2. **Postgres with the pgvector extension** — the only supported database. Plain
   Postgres won't work: migrations create `vector(n)` columns and HNSW indexes that
   require the extension (see spec 007). Locally/in CI this is the bundled `postgres`
   service (`pgvector/pgvector:pg16` image). **In production, this project uses an
   external managed instance (Supabase) instead** — the local `postgres` service is
   never started in production (see the Manual Deploy Runbook's `--no-deps` flag
   below); `DB_*` in the host's `.env` points at Supabase directly. Note: Supabase
   installs the `vector` extension into its own `extensions` schema rather than
   `public`, which `config/database.php`'s `search_path` (`public,extensions`)
   already accounts for — no extra setup needed beyond pointing `DB_*` at it.
3. **A queue worker** (`queue` service, same image as `app`) — processes background
   jobs (currently: book embedding generation, spec 007/008). Nothing in this app is
   synchronous-only; if the queue worker isn't running, embeddings silently never
   generate and AI recommendations/search degrade without erroring loudly.
4. Nothing else. There is no scheduler service — this project's overdue-loan status is
   derived on read, not emailed proactively (see spec 005) — and no cache/session
   service beyond Postgres itself (`CACHE_STORE`/`SESSION_DRIVER`/`QUEUE_CONNECTION`
   are all `database` by default; no Redis/Memcached is required to run this app).

All four map directly onto `docker-compose.yml`'s `app`, `web`, `postgres`, and `queue`
services. Locally, `docker-compose.override.yml` is auto-loaded on top and adds a fifth,
dev-only `vite` service for HMR — production never runs `vite`; the production image
bakes a built `public/build` at image build time instead (see Dockerfile).

## Environment Variables

Full list is `.env.example`; this section documents _purpose_, never real example
values. See also `needed_variable.md` for which of these still need a real value
supplied by you before a live deployment.

| Variable                                                                                                               | Purpose                                                                                                                                                                                                                                                                              |
| ---------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `APP_NAME`                                                                                                             | Display name used in emails, page titles.                                                                                                                                                                                                                                            |
| `APP_ENV`                                                                                                              | `local` / `production` — affects Laravel's default error verbosity and a few framework behaviors.                                                                                                                                                                                    |
| `APP_KEY`                                                                                                              | Laravel's encryption key (session/cookie signing, encrypted columns). Generate a fresh one per environment via `php artisan key:generate` — never reuse the local dev key in production.                                                                                             |
| `APP_DEBUG`                                                                                                            | Must be `false` in production — `true` leaks stack traces/env values to end users on error pages.                                                                                                                                                                                    |
| `APP_URL`                                                                                                              | The app's public base URL. Used for generating absolute links (password reset emails, etc).                                                                                                                                                                                          |
| `DB_CONNECTION` / `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD`                                | Postgres connection. `DB_HOST=postgres` (the Compose service name) in Docker; `DB_PASSWORD` must match `POSTGRES_PASSWORD` used by the `postgres` service in `docker-compose.yml`.                                                                                                   |
| `DEMO_LOGIN_ENABLED`                                                                                                   | Enables a one-click demo login (spec 002). **Must be `false`** for any deployment shown to real, untrusted users.                                                                                                                                                                    |
| `SESSION_DRIVER` / `SESSION_LIFETIME` / `SESSION_ENCRYPT` / `SESSION_PATH` / `SESSION_DOMAIN`                          | Session storage/cookie config. `SESSION_DRIVER=database` by default — no Redis required.                                                                                                                                                                                             |
| `QUEUE_CONNECTION`                                                                                                     | `database` — the queue worker polls the `jobs` table. No external queue broker required.                                                                                                                                                                                             |
| `CACHE_STORE`                                                                                                          | `database` — no Redis/Memcached required.                                                                                                                                                                                                                                            |
| `MAIL_MAILER` / `MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` / `MAIL_PASSWORD` / `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` | Outbound mail (password resets). `log` driver locally (writes to the log file instead of sending). Only needs real values if reset emails must actually deliver.                                                                                                                     |
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` / `AWS_DEFAULT_REGION` / `AWS_BUCKET`                                    | Only needed if cover-image uploads should live on S3 instead of local disk (`FILESYSTEM_DISK`). Not required for an MVP/demo.                                                                                                                                                        |
| `LOAN_PERIOD_DAYS`                                                                                                     | Business rule: how many days a checkout is due before it's overdue (spec 005).                                                                                                                                                                                                       |
| `LLM_PROVIDER` / `LLM_BASE_URL` / `LLM_API_KEY` / `CHAT_MODEL` / `EMBEDDING_MODEL` / `VECTOR_DIM`                      | AI provider config for RAG search and recommendations (spec 007/008), OpenAI-compatible endpoint. `VECTOR_DIM` is baked into a pgvector column width by migration — changing it later requires a new migration and a full re-embed. `LLM_API_KEY` is a real secret; never commit it. |

None of these are baked into the Docker image at build time — they're all read at
container **start** by `docker-entrypoint.sh` (`config:cache`, then `migrate --force`
on the `app` container only). Changing any of them only ever requires
`docker compose up -d --force-recreate`, never an image rebuild. See the Dockerfile's
own top-of-file comment for why.

## Database / pgvector Setup

Items 1-4 below describe the bundled `postgres` service, used locally and in CI. **In
production this project points at an external managed Postgres+pgvector instance
(Supabase) instead** — the local `postgres` service is never started there (see
`--no-deps` in the Manual Deploy Runbook). Item 1's requirement (pgvector available)
still applies; Supabase provides it as an enable-able extension. One thing that does
carry over regardless of where Postgres runs: some managed providers, Supabase
included, install the `vector` extension into a dedicated `extensions` schema rather
than `public` — `config/database.php`'s `search_path` (`public,extensions`) is what
makes the bare `vector` type/`<=>` operator resolve correctly against that layout; it's
a harmless no-op against the local image, where the extension already lives in
`public`.

1. `postgres` runs the `pgvector/pgvector:pg16` image (not plain `postgres`) — this is
   what makes the `vector` extension available at all. Nothing else is
   pgvector-specific about the setup; migrations run `CREATE EXTENSION IF NOT EXISTS
vector` themselves (see spec 007's migration).
2. `POSTGRES_DB` / `POSTGRES_USER` / `POSTGRES_PASSWORD` (set on the `postgres` service
   in `docker-compose.yml`, sourced from `.env`) must match `DB_DATABASE` /
   `DB_USERNAME` / `DB_PASSWORD` used by `app`/`queue` to connect — the Compose file
   defaults `POSTGRES_DB`/`POSTGRES_USER` to `library` but leaves `POSTGRES_PASSWORD`
   required with no default, precisely so a real deployment can't accidentally run with
   an empty/default password.
3. Migrations run automatically at container start on the `app` service only (never
   `queue`, to avoid both containers racing to create the `migrations` table itself —
   see `docker-entrypoint.sh`'s comment). There is no separate manual migration step in
   normal operation.
4. `postgres`'s healthcheck (`pg_isready`) gates `app`/`queue` startup via
   `depends_on: condition: service_healthy` — they won't attempt to connect (and
   migrate) before Postgres is actually accepting connections.
5. Data persists in the named `pgdata` volume, independent of container
   recreate/rebuild. Nothing in normal deploy/rollback operations touches this volume.

## Queue Worker Operation

- The `queue` service runs the same image as `app` (production target), just with a
  different command.
- **Production** (`docker-compose.yml`'s base command, used whenever
  `docker-compose.prod.yml` is layered on top — see below): `php artisan queue:work
--tries=3`. Retries a failing job up to 3 times before it lands in the `failed_jobs`
  table.
- **Local dev** (`docker-compose.override.yml` overrides this): `php artisan
queue:listen --tries=3` instead — reloads on job-class code changes, at the cost of
  being slower per-job than `queue:work`. This override never applies in production
  since `docker-compose.override.yml` is only auto-loaded, not explicitly layered onto
  the prod command.
- To confirm the queue worker is actually processing jobs: `docker compose -f
docker-compose.yml -f docker-compose.prod.yml logs -f queue` and watch for job
  `RUNNING`/`DONE` lines (e.g. `App\Jobs\GenerateBookEmbedding`), or `docker compose ps
queue` to confirm the container is `Up` at all (a crashed/exited queue container
  means jobs silently queue up and never process — no error surfaces to the end user).
- Failed jobs land in the `failed_jobs` table; there's no automated alerting on
  failures yet (not in scope for this spec — see "Not Yet Implemented" below).

## Build Process

The Dockerfile is a multi-stage build; see its own top-of-file comment for the full
rationale. In short:

- `base` — shared PHP-FPM + extensions (`pdo_pgsql`, `pgsql`, `bcmath`, `opcache`,
  `pcntl`), nothing app-specific.
- `dev` — used only by `docker-compose.override.yml` locally. Composer deps installed,
  but no application source baked in (bind-mounted instead).
- `build` — production-only intermediate stage. Has both PHP and Node because Laravel
  Wayfinder's Vite plugin shells out to `php artisan wayfinder:generate` during `npm
run build`. Produces `vendor/`, `public/build`, and cached route/view files.
- `production` — the actual runtime image. Copies only `build`'s _outputs_, never
  Node itself, and never `.env`/application secrets (see "Secret Verification" below).

CI's `docker-build` job (`.github/workflows/tests.yml`) builds the `production` target
on every PR and push to `main` and fails the workflow if it doesn't build cleanly —
this is a build-only validation step, it does not push the image anywhere (no registry
is configured; see "Not Yet Implemented" below).

To build the production image locally:

```
docker build --target production -t mini-library:local .
```

## Manual Deploy Runbook

This is the by-hand procedure — the automated CD pipeline (below) runs the same steps.
Prerequisites: a Docker-capable host, a checkout of this repo at the desired commit,
and a production `.env` already present in that checkout (see "Secrets Live on the
Host" below — this file is never generated or copied by any deploy tooling).

```
cd /path/to/checkout
git pull   # or checkout the specific commit/tag to deploy
docker login ghcr.io -u <your-github-username>   # one-time per session; prompts for a PAT with read:packages
docker image prune -af
docker compose -f docker-compose.yml -f docker-compose.prod.yml pull app queue
docker compose -f docker-compose.yml -f docker-compose.prod.yml \
    up -d --no-deps app web queue
```

What this does:

- `docker login ghcr.io` authenticates the pull below. The automated CD job does this
  itself using the workflow's own short-lived `GITHUB_TOKEN` (see below) — a human
  running this by hand needs their own GitHub Personal Access Token with
  `read:packages` scope instead, since `GITHUB_TOKEN` only exists inside a workflow run.
- `docker image prune -af` reclaims disk from previously-pulled images now superseded
  by the one about to be pulled — cheap insurance against the disk slowly filling
  across many deploys. Doesn't touch running containers or named volumes.
- `docker compose ... pull app queue` fetches the image `docker-build` already built
  and validated on GitHub's runners (tagged `latest`, plus the commit SHA — see
  Rollback below) — nothing is compiled or built on this host at all.
- Layers `docker-compose.prod.yml` on top of the base `docker-compose.yml` —
  `docker-compose.override.yml` is _not_ picked up (it's only auto-loaded when no
  `-f` flags are given), so this never accidentally runs the `dev` build target, bind
  mounts, or the `vite` service in production.
- `docker-compose.prod.yml` adds `restart: unless-stopped` to `app`/`web`/`queue` — a
  host reboot or a crashed container restarts on its own.
- `--no-deps app web queue` names only these three services and skips Compose's
  default dependency auto-start — without it, Compose would still create/start the
  local `postgres` service too, purely because `app`/`queue` declare `depends_on:
postgres` in the base compose file. Production uses external Supabase instead (see
  "Required Infrastructure" above), so the local `postgres` container is never run
  on the deploy host at all.
- `docker-entrypoint.sh` runs `config:cache` and `migrate --force` on the `app`
  container at start, per the container-start-not-build-time contract documented
  throughout this repo.

After it's up, confirm with the health checks below.

## Automated Deployment (CD)

**Implemented**, re-scoped into spec 010 by explicit user direction (see that spec's
own re-scoping note) rather than deferred to a follow-up spec — a real deploy target
now exists. Originally this rebuilt the production image from source on the deploy
host itself; that was switched to a registry-based pull after the host's hardware
proved too slow to compile PHP from source within a reasonable time (a real deploy
timed out mid-compile) — see `docker-build` below.

The `docker-build` CI job (runs on every PR/push, build-validation only) additionally
logs into GHCR and pushes the validated image — but only on a real push to `main`,
never on a PR — tagged both `latest` and the commit SHA, using the workflow's own
short-lived `GITHUB_TOKEN` (`packages: write` on that job only; no new secret).

The `deploy` job in `.github/workflows/tests.yml`:

- Runs only on a real push to `main` (`if: github.event_name == 'push' && github.ref ==
'refs/heads/main'` — deliberately checks both, not just the ref, so it can never fire
  on a pull-request run of the same workflow file).
- Only runs after `static-analysis`, `frontend`, `backend-tests`, and `docker-build`
  all succeed (`needs:`) — a broken build or failing test suite blocks deploy the same
  way it would block a manual deploy decision.
- Uses `appleboy/ssh-action` (pinned to a release commit SHA, `v1.2.5`) to SSH into the
  target host and run **exactly the manual runbook above**: `cd $DEPLOY_PATH && git
fetch/merge --ff-only`, `docker login ghcr.io` (using the job's own `GITHUB_TOKEN` —
  `packages: read` on this job, granted fresh every run, never a long-lived credential
  sitting on the host), `docker image prune -af`, `docker compose ... pull app queue`,
  then `docker compose ... up -d --no-deps app web queue`, then polls `GET /up` (via
  `docker compose exec web wget ... http://127.0.0.1/up`, inside the container
  network — no dependency on knowing the host's external port) for up to ~30 seconds
  before failing the job.
- Required GitHub Secrets (referenced only as `${{ secrets.NAME }}` in the workflow —
  no real values live in this repo):
    - `SSH_HOST` — hostname/IP of the deploy target.
    - `SSH_USER` — SSH username on the deploy target.
    - `DEPLOY_SSH_KEY` — private key for SSH auth to the deploy target. The corresponding
      public key must already be authorized on that host (`~/.ssh/authorized_keys` for
      `SSH_USER`).
    - `DEPLOY_PATH` — absolute path on the deploy target where this repo is checked out
      (e.g. `/srv/mini-library`) and where its production `.env` already lives.
      Nothing GHCR-specific needs supplying — pushing and pulling both use the workflow's
      own `GITHUB_TOKEN`, scoped per-job via that job's `permissions:` block.
- Serialized via a `concurrency` group (`production-deploy`) so two merges landing in
  quick succession can't race each other's `git pull`/`docker compose pull`/`up` on the
  same host directory.
- `set -e` in the remote script means any failed step (a merge conflict, a login
  failure, a failed health check) stops the script immediately and the job — and the
  whole workflow run — is marked failed. It does not attempt any cleanup or rollback on
  failure (see "What CD Does Not Do" below).

### Secrets Live on the Host, Not the Pipeline

The deploy job never copies `.env` or any secret file over SSH. The target host is
assumed to already have its own production `.env` sitting in `$DEPLOY_PATH`, managed
and rotated independently of this pipeline (standard practice: secrets live where the
code runs, not in transit through CI). Provisioning that `.env` on a new host for the
first time is a manual, one-time setup step — not something this pipeline does.

### What CD Does and Does Not Do

**Does:**

- Deploys on every push to `main`, after tests/build pass, with no manual SSH needed
  for routine deploys.
- Fails the workflow clearly (non-zero exit, visible in the Actions UI/logs) if the
  build fails, the git merge isn't a fast-forward, or the post-deploy `/up` health
  check doesn't pass within ~30 seconds.
- Never handles or transmits a real secret value itself — `.env` stays host-resident.

**Does not:**

- **No automatic rollback.** A failed health check fails the job loudly, but the
  script does not revert the host to the previous commit/image on failure. Recovery is
  manual (see "Rollback" below).
- **No zero-downtime/blue-green deploy.** `docker compose up -d` recreates containers
  in place; there is a brief window where `app`/`web` may be restarting. Acceptable
  for this project's current scale; not addressed further here.
- **No database backup before migrating.** `migrate --force` runs unconditionally at
  container start (same as every other deploy/restart). A destructive migration would
  need to be caught in review, not by this pipeline.
- **No host provisioning.** Docker/Docker Compose must already be installed on the
  target host, `$DEPLOY_PATH` must already be a valid git checkout with a working
  remote, and the deploy SSH key must already be authorized — this pipeline assumes all
  of that already exists.
- **No DNS/TLS.** Not addressed anywhere in this spec's scope.
- **No secret rotation.** Rotating `DEPLOY_SSH_KEY`, the host's `.env` values, or any
  GitHub Secret is a manual operation outside this pipeline.

## Rollback

Every image `docker-build` pushes is tagged with the commit SHA it was built from, in
addition to `latest` — rollback is a tag switch, not a rebuild:

```
cd $DEPLOY_PATH
git log --oneline -10   # find the last known-good commit SHA
IMAGE_TAG=<previous-sha> docker compose -f docker-compose.yml -f docker-compose.prod.yml \
    pull app queue
IMAGE_TAG=<previous-sha> docker compose -f docker-compose.yml -f docker-compose.prod.yml \
    up -d --no-deps app web queue
```

`IMAGE_TAG` is read by `docker-compose.prod.yml` (`image:
ghcr.io/inferiore/mini-library:${IMAGE_TAG:-latest}`, defaulting to `latest` when
unset — this is exactly what the normal forward-deploy runbook above relies on).
Passing a previous commit's SHA here pulls and runs that exact, previously-validated
image again — nothing is rebuilt.

Then confirm with the health checks below. Once satisfied, either stay pinned to that
`IMAGE_TAG` until the next intentional deploy, or revert `main` to that commit and push
(reverting forward) so the next automated CD run naturally lands back on `latest`
pointing at the right thing — decide deliberately, this pipeline does not do this step
for you. Note this only rolls back the application image; it does not touch the
database (see "No database backup before migrating" above) — a rollback that depends
on a schema/data change being reverted too needs that handled separately.

## Health Checks

- **App**: `GET /up` — Laravel's built-in health-check route (added by the framework
  starter kit, no custom code). Returns HTTP 200 when the app has booted successfully.
  From the host: `curl -f http://localhost:${APP_PORT:-8000}/up` (against the `web`
  service's published port) or, without relying on the host's port mapping, `docker
compose -f docker-compose.yml -f docker-compose.prod.yml exec web wget -qO- 
http://127.0.0.1/up` (hits the container network directly — this is what the CD job
  itself uses). Use `127.0.0.1`, not `localhost`, inside the container: its
  `/etc/hosts` maps `localhost` to both `127.0.0.1` and `::1`, `wget` tries the IPv6
  entry first, and nginx here only listens on `0.0.0.0:80` — `localhost` reliably
  fails with "Connection refused" even when the app is genuinely healthy (reproduced
  and confirmed against a live deploy).
- **Queue worker**: `docker compose -f docker-compose.yml -f docker-compose.prod.yml ps
queue` should show `Up`. There's no HTTP endpoint for queue health — a crashed queue
  container doesn't surface as an app-facing error, it just means jobs silently stop
  processing, so checking `ps`/`logs` after any deploy is worth doing explicitly, not
  just assuming "app is up" implies "queue is up."
- **Database**: production uses external Supabase, not the local `postgres` service
  (which isn't even started there — see `--no-deps` above), so there's no local
  container health check to run. Confirm reachability instead via the app itself:
  `docker compose -f docker-compose.yml -f docker-compose.prod.yml exec app php
artisan migrate:status` succeeding (rather than a connection error) confirms `app`
  can reach Supabase with the credentials in the host's `.env`. `GET /up` passing is
  also a strong signal, since the app fails to boot fully if the DB is unreachable.

## Secret Verification (No Secrets Baked Into the Image)

The production image build copies `docker-entrypoint.sh` and application source, but
never `.env` — `.dockerignore` explicitly excludes `.env`/`.env.*` (allowing only
`.env.example`, which contains no real values), and nothing in the Dockerfile's
`production` stage runs `config:cache` (that happens at container _start_, in
`docker-entrypoint.sh`, using whatever `.env` is supplied via `env_file:` at that
point — never at build time). Verified manually as part of this spec's implementation:

```
# No .env in the image (only .env.example, which is safe/placeholder-only):
docker run --rm <image> sh -c 'find / -maxdepth 3 -iname ".env*" 2>/dev/null'

# No secret-looking values in the image's own process environment (build args,
# ENV instructions) — a clean image should show only base-image/PHP-toolchain
# variables (PHP_VERSION, PATH, etc.), never APP_KEY/DB_PASSWORD/LLM_API_KEY:
docker run --rm <image> env

# No COPY/ADD of .env-like files anywhere in the image's build history:
docker history --no-trunc <image> | grep -iE "APP_KEY|DB_PASSWORD|LLM_API_KEY|COPY \.env|ADD \.env"
# (expect no output)
```

All three were run against a fresh `docker build --target production` during this
spec's implementation with no findings — see the corresponding CI `docker-build` job
for the same build happening on every push/PR (build-only; it does not run these
particular checks automatically, they're a manual spot-check per spec 010's Acceptance
Criteria).

## Troubleshooting

- **Deploy build fails with "No space left on device"**: the host ran out of disk —
  every deploy rebuilds the production image from scratch (no registry), and old
  image layers/build cache accumulate across repeated deploys if nothing prunes them.
  The runbook and CD job both prune before building now (see above), but if the disk
  is already full when this happens, pruning as part of the failed run doesn't help —
  SSH in and run `docker system prune -af` (add `--volumes` only if you're certain no
  volume holds data you need — this project has none in production once Supabase is
  the database) manually first, then re-run the deploy.
- **`app`/`queue` won't start, logs show a migration error**: locally, check `DB_*`
  values in `.env` match `POSTGRES_*` on the `postgres` service, and that `postgres` is
  actually healthy (`docker compose ps postgres`). In production (external Supabase),
  the local `postgres` service doesn't exist at all — check instead that `DB_HOST`/
  `DB_PORT`/`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` in the host's `.env` match
  Supabase's actual connection details, and that the host can reach Supabase's network
  (no local `pg_isready` healthcheck to lean on here, since there's no local container).
- **Editing `.env` doesn't seem to take effect**: config is cached at container start
  by `docker-entrypoint.sh`, not read live while a container keeps running — use
  `docker compose up -d --force-recreate` after any `.env` change. This is the
  documented/required path per this project's Docker principle (see the top of
  `docker-compose.yml`): it's guaranteed to pick up the new values, whereas relying on
  a plain `restart` isn't the supported convention here even though the entrypoint does
  technically re-run on any container start.
- **Jobs never seem to process**: confirm the `queue` container is `Up`, not exited —
  `docker compose ps queue` / `docker compose logs queue`.
- **`git merge --ff-only` fails during a CD run**: the host's `$DEPLOY_PATH` checkout
  has local commits/changes that aren't on `main` — investigate manually on the host
  before re-running deploy; this is deliberately not auto-resolved (see the deploy
  job's own comment in `.github/workflows/tests.yml`).
- **Deploy job's health check fails after a successful build**: the app built but
  didn't come up healthy — check `docker compose logs app web` on the host directly;
  the CD job's failure alone won't tell you _why_ `/up` didn't respond, only that it
  didn't.

## Not Yet Implemented

These are genuinely out of scope as of this change — not silently deferred, not
partially built:

- **Zero-downtime / blue-green deploys.**
- **Database backup-before-migrate.**
- **Automatic rollback on failed health check.** The CD job fails loudly; it does not
  revert the host on its own.
- **Host provisioning / infrastructure-as-code.** Standing up a new deploy target
  (installing Docker, cloning the repo, creating its `.env`, authorizing the deploy SSH
  key) is entirely manual today.
- **DNS / TLS.**
- **Secret rotation automation.** Rotating any credential (including `LLM_API_KEY` —
  see `needed_variable.md`, which already recommends rotating the one currently in use)
  is a manual step on the host and in GitHub Secrets.
- **Alerting on failed jobs/deploys.** Failed queue jobs land in `failed_jobs` with no
  notification; a failed CD run is only visible by checking the Actions tab.
