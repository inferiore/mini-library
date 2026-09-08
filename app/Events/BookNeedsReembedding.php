<?php

namespace App\Events;

use App\Models\Book;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a book's embedding-relevant fields change (see BookObserver).
 * No listener exists until spec 007 (RAG) registers one — an event with no
 * listeners is a harmless no-op, which is exactly the "no hard dependency on
 * spec 007" behavior 003 needs, without referencing a class that doesn't
 * exist yet.
 */
class BookNeedsReembedding
{
    use Dispatchable;

    public function __construct(public readonly Book $book) {}
}
