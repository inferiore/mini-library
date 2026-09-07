# 003 — Book Management

Status: draft
Area: backend+frontend
Depends on: 001-project-foundation, 002-authentication

## Objective

Let ADMIN and LIBRARIAN users create, edit, delete, and view books in the catalog, and
let all authenticated users browse/view individual books. This is the core catalog
data every later spec (inventory, checkout, search, RAG) reads from.

## User Story

As a librarian, I want to add, edit, and remove books from the catalog, so that the
library's inventory reflects what's physically available. As a member, I want to
browse and view book details, so that I can decide what to borrow.

## Functional Requirements

1. ADMIN/LIBRARIAN can create a book with: title, author, ISBN, description,
   publication year, category, publisher, cover image (optional), total copies.
2. On creation, `available_copies` is initialized equal to `total_copies` (no copies
   are pre-borrowed for a brand-new book).
3. ADMIN/LIBRARIAN can edit any book field except `available_copies` directly — that
   field is only ever mutated by inventory/checkout logic (spec 004/005), not hand-edited.
4. ADMIN/LIBRARIAN can delete (soft-delete) a book. A book with active loans cannot be
   hard-deleted, ever — soft-delete preserves loan history referential integrity.
5. All authenticated users (any role) can list books and view a single book's detail
   page, including its current availability (`available_copies`/`total_copies`).
6. Creating/editing a book with a changed title/author/description/category/publisher
   marks its RAG document stale (sets `rag_documents.status = pending` and dispatches
   the embedding job) — this spec only needs to fire that hook; the RAG machinery
   itself is spec 007's responsibility. If spec 007 isn't implemented yet when 003
   ships, this hook is a no-op guarded by a feature check, not a hard dependency.
7. A `BookFactory` and `BookSeeder` populate a realistic demo catalog (varied
   categories, authors, publishers, publication years, and a mix of fully-available,
   partially-checked-out, and fully-unavailable books) — this is what makes search
   (006), recommendations (008), and checkout (005) demonstrable out of the box instead
   of requiring manual data entry before the app is usable.

## Non-Functional Requirements

- Book listing must be efficient at catalog sizes typical of a small library (hundreds
  to low thousands of rows) — paginate, don't load the whole catalog into one Inertia
  response.
- Cover image uploads (if provided) are validated for type/size and stored via
  Laravel's filesystem abstraction (`local`/`public` disk), never trusting the
  client-supplied MIME type alone.

## User Flow

1. Librarian navigates to Books → "Add Book," fills the form, submits → redirected to
   the new book's detail page.
2. Librarian navigates to a book's detail page → "Edit" → updates fields → saves.
3. Librarian deletes a book from the list or detail page → confirmation → soft-deleted,
   removed from default listings.
4. Member browses Books → clicks a book → sees full detail including availability.

## Database Changes

- `books` migration: `id` (bigint PK), `title` (string, indexed), `author` (string,
  indexed), `isbn` (string, unique, nullable — some catalog entries may predate ISBN
  assignment), `description` (text, nullable), `published_year` (unsigned smallint,
  nullable), `category` (string, indexed, nullable), `publisher` (string, nullable),
  `cover_path` (string, nullable — storage path, not a public URL), `total_copies`
  (unsigned integer), `available_copies` (unsigned integer), `deleted_at`
  (SoftDeletes), timestamps.
- Raw-SQL CHECK constraint: `available_copies >= 0 AND available_copies <= total_copies`.
- Indexes on `title`, `author`, `category` (full-text/search indexes land in spec 006;
  this spec adds plain B-tree indexes sufficient for exact/prefix lookups and sorting).

## API/Application Changes

- `BookPolicy`: `create`/`update`/`delete` → `ADMIN`/`LIBRARIAN` only; `viewAny`/`view`
  → any authenticated user.
- `app/Http/Requests/StoreBookRequest.php`, `UpdateBookRequest.php` — validation per
  below; `UpdateBookRequest` explicitly excludes `available_copies` from the
  fillable/validated fields.
- `App\Services\BookService`: `create()`, `update()`, `delete()` — `update()` diffing
  embedding-relevant fields to decide whether to touch the RAG document (delegates the
  actual dispatch to a `BookObserver` so the hook exists in one place, not duplicated
  across create/update paths).
- `App\Observers\BookObserver`: on `created`/`updated` (when embedding-relevant fields
  changed), no-ops safely if the RAG feature (spec 007) isn't present yet — implemented
  as a guarded call, not a hard dependency that breaks 003 if run standalone.
- `BookController` (resource controller): `index`, `show`, `create`, `store`, `edit`,
  `update`, `destroy` — thin, delegates to `BookService`.
- `database/factories/BookFactory.php` — Faker-backed, varied categories (fiction,
  non-fiction, sci-fi, technical, biography, etc.), realistic ISBN-13 format,
  `total_copies` between 1–10, `available_copies` defaulting to `total_copies` (states
  for "partially checked out" and "fully unavailable" via factory states, e.g.
  `->partiallyBorrowed()`, `->fullyBorrowed()`, used by the seeder to produce a mixed
  catalog).
- `database/seeders/BookSeeder.php` — creates ~30–50 books spanning categories, a mix
  of availability states (per the factory states above), called from
  `DatabaseSeeder`. Never runs in `phpunit.ci.xml`/`phpunit.xml` test environments
  (tests build their own fixtures via the factory directly, not the seeder) and is
  safe to re-run (idempotent — e.g. skip if `books` already has rows, mirroring this
  project's existing user-seeder convention of being safe to re-run).

## UI Changes

- `resources/js/pages/books/index.tsx` — paginated list, search/filter controls wired
  up fully in spec 006 (003 ships the page shell + basic list).
- `resources/js/pages/books/show.tsx` — detail view with availability badge.
- `resources/js/pages/books/create.tsx`, `edit.tsx` — forms, visible/submittable only
  for ADMIN/LIBRARIAN (also enforced server-side via Policy, UI hiding is not the
  security boundary).

## Authorization

- `viewAny`/`view`: any authenticated user (`MEMBER`, `LIBRARIAN`, `ADMIN`).
- `create`/`update`/`delete`: `ADMIN`, `LIBRARIAN` only. Enforced via `BookPolicy` +
  `authorize()` in the Form Requests/controller — not just hidden UI.

## Validation Rules

- `title`, `author`: required, string, max 255.
- `isbn`: nullable, string, max 20, unique on `books` (ignoring soft-deleted? — no:
  unique check includes soft-deleted rows to avoid ISBN collisions with historical
  entries; re-adding a previously deleted ISBN requires restoring, not duplicating).
- `description`: nullable, string, max 5000.
- `published_year`: nullable, integer, between 1450 and current year.
- `category`, `publisher`: nullable, string, max 255.
- `cover_image` (upload): nullable, image, max 2MB, mimes jpg/jpeg/png/webp.
- `total_copies`: required, integer, min 0.
- `available_copies`: never accepted from client input on create or update (server sets
  it: `= total_copies` on create; untouched on edit unless a future spec explicitly
  reconciles it — see 004 for the one exception, admin-adjusted total copies).

## Edge Cases

- Editing `total_copies` down below the number of copies currently on loan (i.e. below
  `total_copies - available_copies`) must be rejected with a clear validation error —
  handled fully in spec 004, but 003's `UpdateBookRequest` must not silently accept an
  invalid new total in the meantime (delegate the check to `BookService::update()`,
  which spec 004 extends).
- Deleting a book with active (unreturned) loans is blocked with a clear error, not a
  silent soft-delete that orphans an active loan's book reference.
- Uploading a non-image file as a cover is rejected with a validation error, not a
  silently-broken image path.
- ISBN left blank is valid (nullable) — don't force a unique constraint collision on
  multiple `NULL` ISBNs (standard SQL behavior: `NULL` isn't equal to `NULL` for
  uniqueness, confirm this holds on both SQLite and Postgres).

## Acceptance Criteria

- ADMIN/LIBRARIAN can create a book; `available_copies` is set to `total_copies`
  automatically.
- MEMBER cannot create/edit/delete a book (403, both via direct request and hidden in
  UI).
- Editing a book never allows `available_copies` to be set directly via the request
  payload.
- Deleting a book with an active loan is blocked; deleting one with none succeeds
  (soft-delete).
- Book list and detail pages are visible to all authenticated roles.
- `php artisan db:seed` populates a realistic, varied demo catalog (multiple
  categories, a mix of available/partially-borrowed/fully-borrowed books) without
  erroring on a re-run.

## Test Cases

1. Feature test: LIBRARIAN creates a book, `available_copies === total_copies`.
2. Feature test: MEMBER attempting create/update/delete gets 403.
3. Feature test: update request including `available_copies` in the payload is ignored
   (DB value unchanged by that field).
4. Feature test: deleting a book with an active loan fails with a clear error; deleting
   one with no active loans soft-deletes it.
5. Feature test: invalid `published_year` / oversized `description` / non-image cover
   are rejected with validation errors.
6. Feature test: book index/show pages return 200 for MEMBER, LIBRARIAN, and ADMIN.
7. Unit test: `BookObserver` marks the RAG document stale / dispatches the embedding
   job only when an embedding-relevant field actually changed (not on every save).
8. Seeder test: running `BookSeeder` populates books across multiple categories with a
   mix of availability states, and running it twice doesn't error or duplicate rows.

## Definition of Done

- Migrations run cleanly on both the fast local SQLite suite and (where Postgres-only
  behavior is involved) `phpunit.ci.xml`.
- All Acceptance Criteria met.
- All Test Cases covered by passing automated tests.
- `composer test` passes (pint, phpstan, PHPUnit).
- No hardcoded secrets introduced.
- `qa-validator` has returned `STATUS: PASSED`.
