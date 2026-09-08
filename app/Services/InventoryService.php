<?php

namespace App\Services;

use App\Exceptions\InsufficientAvailableCopiesException;
use App\Models\Book;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * The single choke point for changing a book's total_copies.
     *
     * Applies the change via one guarded atomic `UPDATE ... WHERE` (never a
     * read-then-modify-then-write), so two concurrent adjustments — or an
     * adjustment racing spec 005's checkout — can't corrupt the invariant
     * (0 <= available_copies <= total_copies). The delta is applied to both
     * columns at once, keeping available_copies <= total_copies by
     * construction; the WHERE clause is what keeps available_copies >= 0 on a
     * decrease.
     */
    public function adjustTotalCopies(Book $book, int $newTotal): Book
    {
        return DB::transaction(function () use ($book, $newTotal): Book {
            $delta = $newTotal - $book->total_copies;

            // On a decrease (delta < 0) the row is only touched while it still
            // has at least abs(delta) available copies to retire; on an
            // increase the guard collapses to `available_copies >= 0`, which is
            // always true. incrementEach applies the delta to both columns in a
            // single UPDATE against the DB's *current* values (not the possibly
            // stale in-memory model), so a losing concurrent request either
            // composes correctly or is rejected — never both.
            $affected = Book::query()
                ->whereKey($book->getKey())
                ->where('available_copies', '>=', max(0, -$delta))
                ->incrementEach([
                    'total_copies' => $delta,
                    'available_copies' => $delta,
                ]);

            if ($affected === 0) {
                // The guard rejected the decrease: not enough available copies.
                // Report how many are currently on loan (total - available)
                // from the authoritative DB state so the message is accurate
                // even under a race.
                $current = $book->fresh() ?? $book;
                $onLoan = $current->total_copies - $current->available_copies;

                throw new InsufficientAvailableCopiesException($onLoan);
            }

            return $book->refresh();
        });
    }
}
