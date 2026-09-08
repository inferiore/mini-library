# 006 — Search

Status: implemented
Area: backend+frontend
Depends on: 001-project-foundation, 003-book-management

## Objective

Let any authenticated user search the catalog by title, author, ISBN, category, or
publisher (and match within description), using PostgreSQL's native full-text search,
without introducing external search infrastructure (Elasticsearch, Algolia, etc.) that
this project's scale doesn't warrant. Clearly distinct from spec 008's AI
recommendations — this is exact/lexical search, not semantic.

## User Story

As a member, I want to search the catalog by keyword, so that I can quickly find a
specific book or browse by category/author without scrolling the entire list.

## Functional Requirements

1. A single search input matches against `title`, `author`, `isbn`, `category`,
   `publisher`, and `description`, ranked with title/author matches weighted higher
   than description matches.
2. Category and publisher can additionally be used as discrete filters (dropdown/facet),
   combinable with the free-text query.
3. Search is paginated identically to the plain book listing from spec 003 (same page
   size, same Inertia pagination component).
4. An empty query returns the full (paginated) catalog, equivalent to spec 003's plain
   listing — search doesn't replace browsing, it augments it.
5. ISBN search matches on exact or partial ISBN string, not just full-text tokens
   (ISBNs don't tokenize meaningfully as prose).

## Non-Functional Requirements

- Search must use Postgres's built-in full-text search (`tsvector`/`tsquery`,
  `GIN` index) — no external search service. On SQLite (fast local PHPUnit driver),
  fall back to `LIKE`-based matching so search logic is still testable without
  Postgres; the ranking/weighting behavior itself is Postgres-only and validated via
  `phpunit.ci.xml`.
- Search response time should stay well under a second at this project's expected
  catalog size (low thousands of rows) — a GIN index is sufficient, no need for
  external infra to hit that bar.

## User Flow

1. User types a query into the search box on the Books page → results update
   (server round-trip on submit/debounce, not a separate page) → ranked results shown,
   most relevant first.
2. User additionally picks a category filter → results narrow to that category,
   combined with the text query.
3. User clears the query → full catalog listing returns.

## Database Changes

- `books` migration (additive, Postgres-only path): a generated `tsvector` column
  `search_vector`, combining `title`/`author` (weight A), `category`/`publisher`
  (weight B), `description` (weight C), maintained via a Postgres trigger or a
  generated column (`GENERATED ALWAYS AS (...) STORED`, Postgres 12+) — prefer the
  generated column over a trigger since it can't drift from the source columns.
- `GIN` index on `search_vector`.
- Both the generated column and its index are guarded to `pgsql` only; on `sqlite` the
  migration skips them and the search query falls back to `LIKE '%term%'` across the
  same field set.
- Plain B-tree index on `isbn` (already added in spec 003) covers exact/partial ISBN
  lookups.

## API/Application Changes

- `App\Services\BookSearchService::search(?string $query, ?string $category = null,
?string $publisher = null): LengthAwarePaginator` — built entirely on Eloquent
  (`Book::query()`), not the plain `DB` facade, so results are real `Book` models
  (casts, soft-delete scoping, accessors all apply) ready to hand straight to the
  Inertia response/paginator. `DB::connection()->getDriverName()` is used only to pick
  which raw SQL fragment to inject via `whereRaw()`/`orderByRaw()`, not to switch query
  builders: Postgres path — `whereRaw('search_vector @@ websearch_to_tsquery(?)',
[$query])` + `orderByRaw('ts_rank(search_vector, websearch_to_tsquery(?)) desc',
[$query])`; SQLite path — `where(fn ($q) => $q->where('title', 'like', "%{$query}%")
->orWhere('author', 'like', ...)->orWhere(...))`, unranked (or a simple "title match
  first" boost, not full relevance). Category/publisher filters are plain
  `->when($category, fn ($q) => $q->where('category', $category))` on the same
  Eloquent builder, composing normally with either branch above.
- `BookController@index` (from spec 003) is extended to accept `query`, `category`,
  `publisher` query-string params and delegate to `BookSearchService` when any are
  present, otherwise the plain listing.

## UI Changes

- Books index page (from 003): search input + category/publisher filter controls
  above the list, results update via Inertia's partial reload (preserving pagination
  state in the URL query string so results are shareable/bookmarkable).

## Authorization

- Same as spec 003's `viewAny`: any authenticated user (`MEMBER`, `LIBRARIAN`,
  `ADMIN`).

## Validation Rules

- `query`: nullable, string, max 255 (prevent pathological input).
- `category`, `publisher`: nullable, string, must match an existing distinct value if
  provided as a filter (not strictly enforced — an unmatched filter value just yields
  zero results, not a validation error, since filters are populated from existing data
  and a stale bookmark shouldn't 422).

## Edge Cases

- A query with only whitespace behaves identically to an empty query (full listing).
- Special characters in the query (e.g. `&`, `:`, `'`) must not break
  `websearch_to_tsquery` parsing or the `LIKE` fallback (escape appropriately for each
  path).
- Searching an ISBN substring (not the full ISBN) still finds the book via the
  `LIKE`/exact-match path, even though it wouldn't tokenize well through full-text
  search alone.
- A category/publisher filter with no matching books returns an empty (not erroring)
  paginated result.

## Acceptance Criteria

- Searching by title, author, ISBN, category, or publisher each independently returns
  the expected book(s).
- Title/author matches rank above description-only matches on Postgres.
- Combining a text query with a category filter narrows correctly.
- Empty query returns the full paginated catalog.
- The same search endpoint works correctly on both the SQLite fallback path and the
  Postgres full-text path (validated via `phpunit.ci.xml` for the latter).

## Test Cases

1. Feature test: search by exact title substring returns the matching book(s).
2. Feature test: search by author name returns the matching book(s).
3. Feature test: search by full and partial ISBN returns the matching book.
4. Feature test: search by category filter alone, and combined with a text query.
5. Feature test: search by publisher filter.
6. Feature test: empty/whitespace-only query returns the full paginated listing.
7. Feature test (Postgres-only, `phpunit.ci.xml`): a title match ranks above a
   description-only match for the same query term.
8. Feature test: special characters in the query don't cause a 500.

## Definition of Done

- Migrations run cleanly on both the fast local SQLite suite and (where Postgres-only
  behavior is involved) `phpunit.ci.xml`.
- All Acceptance Criteria met.
- All Test Cases covered by passing automated tests.
- `composer test` passes (pint, phpstan, PHPUnit).
- No hardcoded secrets introduced.
- `qa-validator` has returned `STATUS: PASSED`.
