<?php

namespace Tests\Feature\Books;

use App\Events\BookNeedsReembedding;
use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BookObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_book_dispatches_the_reembedding_event(): void
    {
        Event::fake([BookNeedsReembedding::class]);

        $book = Book::factory()->create();

        Event::assertDispatched(BookNeedsReembedding::class, fn ($event) => $event->book->is($book));
    }

    public function test_updating_an_embedding_relevant_field_dispatches_the_event(): void
    {
        $book = Book::factory()->create(['title' => 'Original Title']);

        Event::fake([BookNeedsReembedding::class]);
        $book->update(['title' => 'Updated Title']);

        Event::assertDispatched(BookNeedsReembedding::class);
    }

    public function test_updating_a_non_embedding_relevant_field_does_not_dispatch_the_event(): void
    {
        $book = Book::factory()->create(['total_copies' => 5, 'available_copies' => 5]);

        Event::fake([BookNeedsReembedding::class]);
        $book->update(['total_copies' => 6, 'available_copies' => 6]);

        Event::assertNotDispatched(BookNeedsReembedding::class);
    }
}
