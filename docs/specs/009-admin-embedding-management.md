# 009 — Admin Embedding Management

Status: approved
Area: backend+frontend
Depends on: 001-project-foundation, 003-book-management, 007-rag

## Objective

Give ADMIN users visibility and control over the RAG embedding pipeline from spec 007:
see every document's status, inspect the actual text being embedded, retry failures,
and manually trigger regeneration — without ever exposing raw vector data to non-admins
(or, per the master doc, at all — vectors are an internal representation, not a
product-facing concept).

## User Story

As an admin, I want to see which books have embeddings pending, completed, or failed,
inspect what text was actually sent for embedding, and retry or regenerate as needed,
so that I can keep the AI recommendation system's retrieval data healthy without
touching the database directly.

## Functional Requirements

1. ADMIN can view a paginated, filterable (by status) list of all `rag_documents`,
   each showing: associated book, status, `embedding_provider`/`embedding_model`,
   `attempts`, `processed_at`, and a truncated preview of `content`.
2. ADMIN can open a single document to see its full `content` text (what was/will be
   sent to the embedding provider) and, if `status = failed`, the full
   `error_message`.
3. ADMIN can trigger "Retry" on a `failed` document: resets `status = pending`,
   `attempts = 0`, clears `error_message`, and re-dispatches
   `GenerateBookEmbedding`.
4. ADMIN can trigger "Regenerate" on any document regardless of status (including
   `completed`) — same effect as Retry, for cases where the source book changed but the
   observer somehow didn't fire, or the admin just wants a fresh embedding.
5. ADMIN can see an at-a-glance summary count per status (e.g. "12 completed, 2 failed,
   1 pending") to spot pipeline health without reading the full list.
6. Raw embedding vector values are never rendered in any admin view or API response —
   only `content`, `status`, and metadata are exposed.

## Non-Functional Requirements

- Retry/Regenerate actions dispatch the job asynchronously, same as the original
  pipeline — an admin clicking "Retry" must not block on a synchronous embedding call.
- This is the only place in the system where `LIBRARIAN`/`MEMBER` must be explicitly
  denied access — embedding internals are an ADMIN-only concern, distinct from
  LIBRARIAN's catalog-management permissions.

## User Flow

1. Admin navigates to "Admin → AI / Embeddings" → sees the status summary and a
   filterable list.
2. Admin filters to `failed` → opens one → reads the `error_message` and the `content`
   that was sent → clicks "Retry" → status flips to `pending` then (async) `processing`
   → `completed` or `failed` again.
3. Admin regenerates a `completed` document after noticing stale content (e.g. the
   observer didn't fire due to a bug fixed in a later spec) → same async flow.

## Database Changes

None beyond spec 007's `rag_documents` table — this spec is purely an admin
UI/controller layer over existing columns (`status`, `attempts`, `error_message`,
`content`, `processed_at`).

## API/Application Changes

- `RagDocumentPolicy`: `viewAny`/`view`/`retry`/`regenerate` → `ADMIN` only.
- `Admin\RagDocumentController@index` — filterable/paginated list +status summary
  counts.
- `Admin\RagDocumentController@show` — single document detail (content, status,
  error, metadata) — no vector data in the response payload, ever.
- `Admin\RagDocumentController@retry` / `@regenerate` — both delegate to
  `RagDocumentService::requeue(RagDocument $doc): void` (reset fields, dispatch job);
  the two actions are the same operation with different entry points/labels per the
  Functional Requirements above (Retry implied only for `failed`, Regenerate available
  always) — enforce that distinction at the Policy/controller level (Retry action
  rejects a non-`failed` document with a clear error; Regenerate accepts any status).
- Route group under `/admin/embeddings`, gated by an `ADMIN`-only route middleware
  group (reusable for future admin-only sections).

## UI Changes

- `resources/js/pages/admin/embeddings/index.tsx` — status summary chips, filterable
  paginated table (book title, status badge, attempts, processed_at, actions).
- `resources/js/pages/admin/embeddings/show.tsx` — full content text, error message
  (if any), Retry/Regenerate buttons contextual to current status.
- Nav: an "Admin" section visible only to ADMIN users, linking to this page (and to
  wherever spec 010/future admin tooling lands).

## Authorization

- `viewAny`, `view`, `retry`, `regenerate`: `ADMIN` only. `LIBRARIAN` and `MEMBER` get
  403 on both the route and any attempt to hit the underlying endpoints directly (not
  just UI-hidden).

## Validation Rules

- `retry`: rejected (with a clear error, not silently ignored) if the document's
  current `status` isn't `failed`.
- `regenerate`: no status precondition — valid from any status.

## Edge Cases

- Retrying a document that a background job is _currently_ processing (status already
  flipped to `processing` by the time the admin's request lands) should not create a
  duplicate in-flight job — check current status inside the same operation that
  dispatches, and reject/no-op if already `processing`.
- A document whose associated book was soft-deleted after the document was created
  still shows in the admin list (marked with the book's soft-deleted state) rather than
  erroring or disappearing silently — admins need to know why a document might not be
  worth regenerating.
- Viewing a document's `content` never triggers embedding as a side effect — read and
  write paths are fully separate.

## Acceptance Criteria

- ADMIN sees accurate status counts and a filterable list matching the actual
  `rag_documents` table state.
- ADMIN can view full `content` and `error_message` for any document.
- Retry only works on `failed` documents and correctly resets state + re-dispatches.
- Regenerate works from any status and correctly resets state + re-dispatches.
- LIBRARIAN and MEMBER get 403 on every route/action in this spec.
- No response in this feature ever includes raw vector data.

## Test Cases

1. Feature test: ADMIN sees the correct status summary counts for a fixture set of
   documents in mixed statuses.
2. Feature test: ADMIN can view a single document's full content/error detail.
3. Feature test: Retry on a `failed` document resets fields and dispatches the job;
   Retry on a `completed`/`pending`/`processing` document is rejected with a clear
   error.
4. Feature test: Regenerate works regardless of current status.
5. Feature test: LIBRARIAN and MEMBER receive 403 on index/show/retry/regenerate.
6. Feature test: the JSON/Inertia response for both the list and detail views contains
   no `embedding` vector field.
7. Feature test: retrying a document already in `processing` status is a no-op/clear
   rejection rather than a duplicate dispatch.

## Definition of Done

- Migrations run cleanly on both the fast local SQLite suite and (where Postgres-only
  behavior is involved) `phpunit.ci.xml`.
- All Acceptance Criteria met.
- All Test Cases covered by passing automated tests.
- `composer test` passes (pint, phpstan, PHPUnit).
- No hardcoded secrets introduced.
- `qa-validator` has returned `STATUS: PASSED`.
