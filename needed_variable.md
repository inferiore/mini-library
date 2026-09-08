# Needed Variables

Values I need from you (or that you should double-check/rotate) before this app is
production-ready. Nothing below blocks local development — the app runs fine locally
with the defaults already in `.env` / `.env.example`. This file itself is **not**
gitignored on purpose (no real secret values live in it) so it stays visible in the
repo as a checklist; fill it in against your own `.env`, don't paste real values here.

## Already have (in local `.env`, gitignored, not committed)

- `LLM_API_KEY` — Gemini key you provided in chat. **Recommend rotating this key**
  before using it anywhere beyond your own local machine, since it was shared in a
  chat transcript rather than a secret manager. Once rotated, just update `.env`
  locally — nothing else needs to change.

## Needed for local Docker Compose (spec 001)

- `POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_PASSWORD` — used by the `postgres` service
  in `docker-compose.yml` and must match `DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` in
  `.env` so the `app`/`queue` containers can connect. Any values are fine locally
  (e.g. `library` / `library` / a random local password) — pick something and I'll
  wire it through, or tell me values you'd prefer.

## Needed before a real (non-local) deployment (spec 010 / DEPLOYMENT.md)

- `APP_URL` — the real public URL once there's a deploy target.
- `APP_KEY` — generate a fresh one for that environment via `php artisan key:generate`
  (never reuse the local one).
- Production `DB_*` credentials for wherever Postgres+pgvector actually runs.
- `DEMO_LOGIN_ENABLED` — **must be set to `false`** for any deployment shown to real,
  untrusted users (see spec 002). Defaults to `true` locally/for demos on purpose.
- `MAIL_*` — only needed if password-reset emails must actually deliver somewhere real
  (currently `log` driver locally, which just writes reset links to the log file).
- If cover-image uploads should live on S3 instead of local disk in production:
  `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`.
  Not required — local disk storage works fine for an MVP/demo.

## Needed for automated deployment (spec 010, re-scoped 2026-09-08 — CD now implemented)

These are **GitHub Actions Secrets** (repo Settings → Secrets and variables → Actions),
not `.env` values — the `deploy` job in `.github/workflows/tests.yml` references them
only as `${{ secrets.NAME }}`. Nothing below has a value in this repo; supply them in
GitHub's own secret store, never in a file:

- `SSH_HOST` — hostname or IP of the deploy target host.
- `SSH_USER` — SSH username on the deploy target.
- `DEPLOY_SSH_KEY` — private key for SSH auth to the deploy target. Generate a
  dedicated deploy keypair (don't reuse a personal key); the matching public key must
  be added to that user's `~/.ssh/authorized_keys` on the target host.
- `DEPLOY_PATH` — absolute path on the deploy target where this repo is checked out
  (e.g. `/srv/mini-library`) and where that host's own production `.env` already lives.
  The deploy job never creates or copies this `.env` — see `DEPLOYMENT.md`'s "Secrets
  Live on the Host, Not the Pipeline."

Until these four secrets are set, the `deploy` job will run (on a push to `main`, after
tests/build pass) and fail at the SSH connection step — that's expected and is not a
sign of a workflow bug; it's simply unconfigured until you supply real values in
GitHub's secret store.

## Deferred (still genuinely out of scope — see DEPLOYMENT.md's "Not Yet Implemented")

- Container registry credentials (e.g. GHCR token) — deploy/rollback both work today by
  rebuilding the production image from source on the target host itself; nothing in
  this pipeline pushes to or pulls from a registry, so no registry credential is
  needed yet. Adding one later would make rollback an instant tag-switch instead of a
  rebuild (see `DEPLOYMENT.md`'s "Rollback" section).

## Nothing needed for

- Embeddings/AI (`LLM_PROVIDER`, `LLM_BASE_URL`, `CHAT_MODEL`, `EMBEDDING_MODEL`,
  `VECTOR_DIM`) — already set from what you provided.
- Queue/cache/session — all database-backed, no external service required.
- SSO — not being built yet (spec 002 only leaves the seam for it); nothing to supply
  until you actually want an SSO provider added.
