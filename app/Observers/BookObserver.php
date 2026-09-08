<?php

namespace App\Observers;

use App\Events\BookNeedsReembedding;
use App\Models\Book;

class BookObserver
{
    public function created(Book $book): void
    {
        BookNeedsReembedding::dispatch($book);
    }

    public function updated(Book $book): void
    {
        if ($book->wasChanged(Book::EMBEDDING_RELEVANT_FIELDS)) {
            BookNeedsReembedding::dispatch($book);
        }
    }
}
