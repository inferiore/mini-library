<?php

namespace App\Services;

use App\Exceptions\AlreadyBorrowedException;
use App\Exceptions\BookUnavailableException;
use App\Exceptions\LoanAlreadyReturnedException;
use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LoanService
{
    /**
     * Check a book out to a member.
     *
     * Uses the atomic guarded-UPDATE pattern from spec 004 (never
     * SELECT ... FOR UPDATE, which SQLite silently ignores): a single
     * `decrement ... WHERE available_copies > 0` whose affected-row count is
     * the source of truth. Under N concurrent attempts on the last copy,
     * exactly one UPDATE affects the row; the rest see 0 affected and are
     * rejected — availability can never go negative.
     */
    public function checkout(Book $book, User $user): Loan
    {
        return DB::transaction(function () use ($book, $user): Loan {
            // Duplicate-active-loan check happens first, inside the same
            // transaction, so a rejected duplicate never touches inventory.
            // The partial unique index is the last line of defense; this gives
            // a clean domain error instead of a DB integrity violation.
            $alreadyBorrowed = Loan::query()
                ->where('book_id', $book->id)
                ->where('user_id', $user->id)
                ->whereNull('returned_at')
                ->exists();

            if ($alreadyBorrowed) {
                throw new AlreadyBorrowedException;
            }

            $affected = DB::table('books')
                ->where('id', $book->id)
                ->where('available_copies', '>', 0)
                ->decrement('available_copies');

            if ($affected === 0) {
                throw new BookUnavailableException;
            }

            return Loan::create([
                'book_id' => $book->id,
                'user_id' => $user->id,
                'checked_out_at' => now(),
                'due_at' => now()->addDays(config('library.loan_period_days')),
            ]);
        });
    }

    /**
     * Return a checked-out book.
     *
     * Idempotency guard first (already-returned loans are rejected, never
     * double-incremented). The increment is guarded by `< total_copies` as
     * defense-in-depth so availability can never exceed the total even from a
     * hypothetical inconsistent state (mirrors spec 004's CHECK constraint).
     */
    public function return(Loan $loan): Loan
    {
        return DB::transaction(function () use ($loan): Loan {
            if ($loan->returned_at !== null) {
                throw new LoanAlreadyReturnedException;
            }

            $loan->update(['returned_at' => now()]);

            DB::table('books')
                ->where('id', $loan->book_id)
                ->where('available_copies', '<', DB::raw('total_copies'))
                ->increment('available_copies');

            return $loan;
        });
    }
}
