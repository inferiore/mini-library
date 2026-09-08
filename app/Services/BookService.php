<?php

namespace App\Services;

use App\Exceptions\BookHasActiveLoansException;
use App\Models\Book;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BookService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Book
    {
        $book = new Book($attributes);
        // available_copies is never client-supplied (see StoreBookRequest) —
        // a brand-new book always starts fully available.
        $book->available_copies = $attributes['total_copies'];
        $book->save();

        return $book;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Book $book, array $attributes): Book
    {
        if (array_key_exists('total_copies', $attributes)
            && $attributes['total_copies'] < $book->available_copies) {
            // Full "N copies are on loan" guard lands in spec 004 (inventory)
            // once available_copies can differ from total_copies via real
            // checkouts. This is the minimum guard 003 needs on its own: the
            // invariant (available_copies <= total_copies) must never break,
            // even before checkout exists.
            throw ValidationException::withMessages([
                'total_copies' => 'Total copies cannot be less than the currently available copies.',
            ]);
        }

        $book->fill($attributes);
        $book->save();

        return $book;
    }

    public function delete(Book $book): void
    {
        if ($this->hasActiveLoans($book)) {
            throw new BookHasActiveLoansException;
        }

        $book->delete();
    }

    /**
     * No loans exist until spec 005 creates the `loans` table — guarded the
     * same way BookObserver guards the (also not-yet-existing) RAG hook.
     * Queries the raw table (not an Eloquent model) since App\Models\Loan
     * doesn't exist yet either; the column shape here matches spec 005's
     * planned `loans` schema exactly.
     */
    private function hasActiveLoans(Book $book): bool
    {
        if (! Schema::hasTable('loans')) {
            return false;
        }

        return DB::table('loans')
            ->where('book_id', $book->id)
            ->whereNull('returned_at')
            ->exists();
    }
}
