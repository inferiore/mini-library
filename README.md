# Mini Library

A library management system (Laravel + Inertia/React) for cataloging books, tracking
checkouts, and searching the collection — with AI-powered, natural-language book
recommendations grounded in the real catalog (no invented titles).

Core features: book catalog management, checkout/check-in with concurrency-safe
inventory tracking, full-text catalog search, AI recommendations, and an admin view
into the embedding pipeline that powers them.

## AI Recommendations

Instead of only exact keyword search, you can describe what you're looking for in
plain natural language (e.g. _"I want to read a book for software engineering"_) and
get relevant picks pulled from the actual catalog, each with a short explanation of why
it matches.

![AI Recommendations widget: a natural-language query returns real catalog books with a "Why this matches" explanation and live availability](docs/images/ai-recommendations.png)

## Local setup

Requires Docker and Docker Compose. No local PHP/Node/Postgres install needed — the
whole stack runs in containers.

```bash
git clone <this repo>
cd mini-library
cp .env.example .env
```

Fill in `.env`:

- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` — any values work locally (e.g.
  `library` / `library` / a password of your choice); they're used to provision the
  local Postgres container and must match what the app connects with.
- `LLM_API_KEY` — a Gemini API key (see below). Required for search ranking, AI
  recommendations, and the embedding pipeline; the app still runs without it, but
  those features won't work.

Then bring the stack up:

```bash
docker compose up -d --build
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
```

- App: http://localhost:8000
- Vite dev server (HMR): http://localhost:5173

`db:seed` creates 3 demo accounts (`admin@library.test`, `librarian@library.test`,
`member@library.test`, all password `password`) and a real 20-book catalog (see
`database/data/real_books.json`). Log in at `/login` — a one-click demo-login panel is
shown when `DEMO_LOGIN_ENABLED=true` (the local default).

Seeding a fresh book automatically dispatches an embedding job to the `queue`
container; give it a few seconds after seeding before search ranking/recommendations
reflect the full catalog.

### Getting a Gemini API key (for local use)

The AI features (embeddings, search ranking, recommendations) call Gemini through its
OpenAI-compatible endpoint.

1. Go to [Google AI Studio](https://aistudio.google.com/app/apikey).
2. Sign in with a Google account and click **Create API key**.
3. Copy the key into `.env` as `LLM_API_KEY`.

`.env.example` already has the matching provider settings:

```
LLM_BASE_URL=https://generativelanguage.googleapis.com/v1beta/openai
CHAT_MODEL=gemini-3.6-flash
EMBEDDING_MODEL=gemini-embedding-001
VECTOR_DIM=1536
```

This is a personal API key tied to your Google account — free-tier quota is enough for
local development, but don't commit it or share it outside your own `.env` (which is
gitignored). See `needed_variable.md` for the full list of variables this project
needs and why.

## More docs

- `docs/specs/` — the full set of specs this project was built from.
- `DEPLOYMENT.md` — production build, manual deploy runbook, and the automated CD
  pipeline.
- `needed_variable.md` — every environment variable/secret this project needs, and
  when.
