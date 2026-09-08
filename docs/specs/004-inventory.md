# 004 — Inventory

Status: approved
Area: backend
Depends on: 001-project-foundation, 003-book-management

## Objective

Guarantee inventory integrity: `available_copies` can never go negative or exceed
`total_copies`, and ADMIN/LIBRARIAN can safely adjust `total_copies` after the fact
(adding or removing physical copies) without ever corrupting the invariant. This spec
defines the invariant and the admin adjustment path; spec 005 is what actually moves
`available_copies` via checkout/return.

## User Story

As a librarian, I want to add or remove physical copies of a book from its record, so
that the system's inventory count matches reality, without ever risking a negative or
inconsistent available count.

## Functional Requirements

1. `available_copies` is never directly settable via any user-facing input — it is
   always derived/adjusted by system logic (this spec's total-copies adjustment,
   spec 005's checkout/return).
2. ADMIN/LIBRARIAN can adjust a book's `total_copies` up or down via a dedicated
   "Adjust Inventory" action (not the general book-edit form from 003, to keep the
   invariant-sensitive path isolated and auditable).
3. Increasing `total_copies` by N increases `available_copies` by N (new copies are
   available immediately).
4. Decreasing `total_copies` by N is only allowed if at least N copies are currently
   _available_ (i.e. you can't retire a copy that's out on loan) — decreasing
   `available_copies` by N in that case. Attempting to decrease below the number of
   copies currently on loan is rejected with a clear error explaining how many copies
   would need to be returned first.
5. `available_copies < 0` and `available_copies > total_copies` must be impossible to
   reach through any code path — enforced at both the application layer and the DB
   layer (CHECK constraint from spec 003, kept in force).

## Non-Functional Requirements

- Every inventory adjustment runs inside `DB::transaction()`.
- The CHECK constraint is not a formality — it's the last line of defense if
  application logic ever has a bug; a test in this spec proves the DB itself rejects
  an invalid raw write, independent of the application code.

## User Flow

1. Librarian opens a book's detail page → "Adjust Inventory" → enters a new
   `total_copies` value (or a delta) → confirms.
2. On success, the book's total/available counts update immediately in the UI.
3. On a rejected decrease (not enough available copies to retire), the librarian sees
   exactly how many copies are currently on loan and must wait for returns.

## Database Changes

- No new tables. Reuses `books.total_copies`/`available_copies` and the CHECK
  constraint from spec 003.
- No schema change in this spec beyond what 003 already introduced — this spec is
  entirely about the application-layer guarantee plus a defense-in-depth DB test.

## API/Application Changes

- `App\Services\InventoryService::adjustTotalCopies(Book $book, int $newTotal): Book` —
  the single choke point for changing `total_copies`. Computes the delta, and:
    - delta > 0: atomically `total_copies += delta; available_copies += delta`.
    - delta < 0: atomically checks `available_copies >= abs(delta)` and, if so,
      `total_copies += delta; available_copies += delta`; otherwise throws
      `InsufficientAvailableCopiesException` carrying how many are currently on loan.
    - All of the above via a single guarded `UPDATE ... WHERE` (not a read-then-write),
      the same atomic pattern spec 005 uses for checkout, so two concurrent adjustments
      (or an adjustment racing a checkout) can't corrupt the invariant.
- `App\Http\Requests\AdjustInventoryRequest` — validates the new `total_copies` value.
- `InventoryController@update` (or a method on `BookController`) — thin, delegates to
  `InventoryService`, catches `InsufficientAvailableCopiesException` and returns a
  clear validation-style error.
- `BookPolicy::adjustInventory` — `ADMIN`/`LIBRARIAN` only (same as `update`, but named
  distinctly so a future spec could split the permission if needed).

## UI Changes

- On the book detail page (from 003): an "Adjust Inventory" control (ADMIN/LIBRARIAN
  only) showing current total/available/on-loan counts, accepting a new total value,
  surfacing the specific "N copies are on loan, can't reduce below that" error inline.

## Authorization

- `adjustInventory`: `ADMIN`, `LIBRARIAN` only. Same enforcement pattern as
  spec 003 (Policy + server-side check, not just UI hiding).

## Validation Rules

- New `total_copies`: required, integer, min 0.
- Rejects (with a Service-level exception surfaced as a validation error, not a 500) any
  decrease that would require `available_copies` to go negative.

## Edge Cases

- Decreasing `total_copies` to exactly the number currently on loan (i.e. resulting
  `available_copies = 0`) is allowed — it's only "reduce _below_ the on-loan count"
  that's rejected, not "reduce to zero available."
- Two concurrent adjustment requests on the same book (e.g. two librarians) must not
  both succeed in a way that violates the invariant — the atomic guarded UPDATE handles
  this the same way spec 005 handles concurrent checkouts.
- An adjustment racing a concurrent checkout (one request increasing total copies while
  another checks the last copy out) must still leave the invariant intact regardless of
  ordering.

## Acceptance Criteria

- Increasing `total_copies` immediately increases `available_copies` by the same
  amount.
- Decreasing `total_copies` when enough copies are available succeeds and decreases
  `available_copies` accordingly.
- Decreasing `total_copies` below the on-loan count is rejected with a specific,
  actionable error (not a generic failure).
- No code path can ever produce `available_copies < 0` or `available_copies >
total_copies` — proven by both application tests and a raw-SQL CHECK-constraint test.
- Concurrent adjustment requests don't corrupt the invariant (tested via
  parallel/rapid-sequential simulated requests).

## Test Cases

1. Feature test: increasing total copies increases available copies by the same delta.
2. Feature test: decreasing total copies with sufficient available copies succeeds.
3. Feature test: decreasing total copies below the on-loan count is rejected with the
   correct "N on loan" message.
4. Feature test: MEMBER cannot access the adjust-inventory action (403).
5. DB-level test: a raw insert/update attempting `available_copies > total_copies` or
   `available_copies < 0` is rejected by the CHECK constraint directly (bypassing
   application code) — proves defense-in-depth actually works, on both SQLite and
   (via `phpunit.ci.xml`) Postgres.
6. Concurrency test: simulate two adjustment requests racing on the same book; assert
   the final state satisfies the invariant and no request silently corrupted state
   (either both succeed correctly and compose, or the losing one gets a consistent
   error/no-op depending on the guarded UPDATE's outcome).

## Definition of Done

- Migrations run cleanly on both the fast local SQLite suite and (where Postgres-only
  behavior is involved) `phpunit.ci.xml`.
- All Acceptance Criteria met.
- All Test Cases covered by passing automated tests.
- `composer test` passes (pint, phpstan, PHPUnit).
- No hardcoded secrets introduced.
- `qa-validator` has returned `STATUS: PASSED`.
