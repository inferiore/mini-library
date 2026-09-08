# 005 — Checkout / Check-in

Status: implemented
Area: backend+frontend
Depends on: 001-project-foundation, 002-authentication, 003-book-management,
004-inventory

## Objective

Let MEMBER users borrow and return books, with a `loans` record tracking each
borrow/return cycle, safe concurrent checkout of the last available copy, and overdue
status computed on read (no stored status column to desync).

## User Story

As a member, I want to check out an available book and return it later, so that I can
read physical books the library owns, with the system always reflecting accurate
availability. As a librarian, I want to see all active/overdue loans, so that I can
manage the collection.

## Functional Requirements

1. A MEMBER can check out a book only if `available_copies > 0` and they don't already
   have an active (unreturned) loan for that same book.
2. Checkout creates a `loans` row (`book_id`, `user_id`, `checked_out_at = now()`,
   `due_at = now() + loan_period_days`, `returned_at = null`) and atomically decrements
   `available_copies` — both inside one `DB::transaction()`.
3. A MEMBER (or LIBRARIAN/ADMIN on their behalf) can return a book they have an active
   loan for. Return sets `returned_at = now()` and atomically increments
   `available_copies` — both inside one `DB::transaction()`.
4. A loan that's already returned cannot be returned again (idempotency: returning
   twice is rejected, not silently double-incrementing availability).
5. "Overdue" is never stored — it's derived: an active loan (`returned_at IS NULL`)
   where `due_at < now()`.
6. LIBRARIAN/ADMIN can view all loans (active, returned, overdue) system-wide; MEMBER
   can view only their own loan history ("My Loans").
7. Concurrent checkout attempts for the last available copy of a book must result in
   exactly one success and the other request(s) receiving a clear "no longer available"
   error — never both succeeding and driving `available_copies` negative.

## Non-Functional Requirements

- Checkout/return are the highest-contention write paths in this system — both use the
  atomic guarded-`UPDATE` pattern from spec 004, not `SELECT ... FOR UPDATE`, because
  SQLite (the fast local/PHPUnit driver) silently ignores row locks and a
  lock-dependent test would give false confidence. The guarded UPDATE
  (`WHERE available_copies > 0`, checking affected-row count) is atomic on both
  SQLite and Postgres.
- `loan_period_days` is a config value (`config('library.loan_period_days')`, default
  14), not hardcoded inline, so it can be tuned without a code change.

## User Flow

1. Member views a book's detail page → "Check Out" (visible/enabled only if
   available and they don't already hold it) → confirms → loan created, availability
   drops by one, button becomes "Return."
2. Member views "My Loans" → sees active and past loans, with overdue ones visually
   flagged → clicks "Return" on an active loan → confirms → loan marked returned,
   availability rises by one.
3. Librarian views "All Loans" → filters by active/overdue/returned → can force-return
   a loan on a member's behalf (e.g. handling an in-person return at the desk).

## Database Changes

- `loans` migration: `id` (bigint PK), `book_id` (FK → `books.id`, restrict on delete),
  `user_id` (FK → `users.id`, restrict on delete), `checked_out_at` (timestamp),
  `due_at` (timestamp), `returned_at` (timestamp, nullable), timestamps.
- Index on `book_id`, index on `user_id`.
- Partial unique index: `UNIQUE(book_id, user_id) WHERE returned_at IS NULL` — blocks a
  member from holding two simultaneous active loans of the same book. (Postgres: a
  standard partial unique index. SQLite: a partial unique index is also supported via
  `CREATE UNIQUE INDEX ... WHERE returned_at IS NULL` since SQLite 3.8+ — confirm
  Laravel's schema builder emits this correctly for both drivers, or fall back to a raw
  statement per driver if not.)
- `config/library.php`: `loan_period_days` (default 14, env-overridable via
  `LOAN_PERIOD_DAYS`).

## API/Application Changes

- `App\Services\LoanService::checkout(Book $book, User $user): Loan`:
    ```php
    DB::transaction(function () use ($book, $user) {
        if (Loan::where('book_id', $book->id)->where('user_id', $user->id)
                ->whereNull('returned_at')->exists()) {
            throw new AlreadyBorrowedException();
        }
        $affected = DB::table('books')->where('id', $book->id)
            ->where('available_copies', '>', 0)->decrement('available_copies');
        if ($affected === 0) throw new BookUnavailableException();
        return Loan::create([
            'book_id' => $book->id, 'user_id' => $user->id,
            'checked_out_at' => now(),
            'due_at' => now()->addDays(config('library.loan_period_days')),
        ]);
    });
    ```
- `App\Services\LoanService::return(Loan $loan): Loan`:
    ```php
    DB::transaction(function () use ($loan) {
        if ($loan->returned_at !== null) throw new LoanAlreadyReturnedException();
        $loan->update(['returned_at' => now()]);
        DB::table('books')->where('id', $loan->book_id)
            ->where('available_copies', '<', DB::raw('total_copies'))
            ->increment('available_copies');
    });
    ```
- `Loan` model: `scopeActive()` (`whereNull('returned_at')`), `scopeOverdue()`
  (`active()->where('due_at', '<', now())`), `isOverdue(): bool` accessor.
- `LoanPolicy`: `create` (checkout) → `MEMBER` only (librarians/admins manage the
  catalog, they don't personally borrow in this model — flagged below for
  confirmation); `viewAny` (all loans) → `ADMIN`/`LIBRARIAN`; `view`/`return` own loan →
  the owning `MEMBER`, or `ADMIN`/`LIBRARIAN` for any loan (desk-assisted returns).
- `LoanController@store` (checkout), `@update` (return), `@index` (list, scoped by role
  in the query, not just hidden in UI).

## UI Changes

- Book detail page (from 003): "Check Out" / "Return" action reflecting the current
  user's relationship to that book (available + not held → Check Out; held by them →
  Return; unavailable → disabled with availability shown).
- `resources/js/pages/loans/my-loans.tsx` — MEMBER's own loan history, overdue ones
  visually flagged (e.g. red due-date badge).
- `resources/js/pages/loans/index.tsx` — ADMIN/LIBRARIAN's all-loans view with
  active/overdue/returned filters and a "Return" action per active loan.

## Authorization

- Checkout: `MEMBER` only (see flag below).
- Return: the loan's own `user_id` (any role, since anyone could be a borrower), or
  `ADMIN`/`LIBRARIAN` on behalf of any member.
- View own loans: the owning user. View all loans: `ADMIN`/`LIBRARIAN`.

**Flag for approval**: the master requirements list "Borrow books / Return books" under
MEMBER permissions and don't explicitly grant LIBRARIAN/ADMIN the ability to
self-checkout. This spec assumes librarians/admins manage the catalog but don't
personally borrow through this system (a real staff member wanting to read a book
would presumably also have a MEMBER-capable account, or this is simply out of scope).
If librarians/admins should also be able to check out books as themselves, say so and
this spec's `LoanPolicy::create` changes to allow any role.

## Validation Rules

- Checkout: the book must exist and not be soft-deleted; no field input beyond the
  book identifier (dates are server-computed, never client-supplied).
- Return: the loan must exist, belong to a book/user context the actor is authorized
  for, and not already be returned.

## Edge Cases

- Checking out the last available copy: exactly one of N concurrent requests succeeds;
  the rest get a clear "no longer available" error, never a negative
  `available_copies`.
- Attempting to check out a book the member already has an active loan for is
  rejected before touching inventory (checked first, inside the same transaction, so a
  duplicate-loan attempt never decrements availability it shouldn't).
- Returning an already-returned loan is rejected with a clear error, and does not
  double-increment `available_copies`.
- Returning a loan increments `available_copies` guarded by `< total_copies` so that,
  even in a hypothetical inconsistent state, availability can never be pushed above the
  total (defense-in-depth mirroring spec 004's CHECK constraint).
- A soft-deleted book with historical loans still displays correctly in "My Loans" /
  "All Loans" (loan history isn't lost when a book is later removed from the catalog).

## Acceptance Criteria

- Checkout creates a loan, sets `due_at` correctly, and decrements availability by
  exactly one.
- Checkout is refused when `available_copies = 0`, with a clear error.
- Checking out a book already held by the same member is refused.
- Return sets `returned_at`, increments availability by exactly one, and is refused if
  already returned.
- Overdue loans are identified purely by `returned_at IS NULL AND due_at < now()` —
  no separate status column exists to desync.
- Under simulated concurrent checkout of the last copy, exactly one request succeeds.
- MEMBER sees only their own loans; ADMIN/LIBRARIAN see all loans.

## Test Cases

1. Feature test: successful checkout — loan created, `due_at` correct,
   `available_copies` decremented by one.
2. Feature test: checkout rejected when `available_copies = 0`.
3. Feature test: checkout rejected for a book the member already actively holds.
4. Feature test: successful return — `returned_at` set, `available_copies` incremented
   by one.
5. Feature test: returning an already-returned loan is rejected and does not change
   `available_copies` a second time.
6. Feature test: a loan with `due_at` in the past and `returned_at = null` is correctly
   flagged overdue by the scope/accessor; a returned loan past its due date is not.
7. Concurrency test: N simultaneous checkout requests against a book with exactly one
   available copy — assert exactly one succeeds, the rest receive the unavailable
   error, and final `available_copies = 0` (never negative).
8. Feature test: MEMBER's "My Loans" index only returns their own loans; ADMIN/
   LIBRARIAN's "All Loans" index returns every user's loans.
9. Feature test: LIBRARIAN/ADMIN can return a loan on behalf of a member; a different
   MEMBER cannot return another member's loan.

## Definition of Done

- Migrations run cleanly on both the fast local SQLite suite and (where Postgres-only
  behavior is involved) `phpunit.ci.xml`.
- All Acceptance Criteria met.
- All Test Cases covered by passing automated tests.
- `composer test` passes (pint, phpstan, PHPUnit).
- No hardcoded secrets introduced.
- `qa-validator` has returned `STATUS: PASSED`.
