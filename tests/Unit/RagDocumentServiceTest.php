<?php

namespace Tests\Unit;

use App\Enums\RagDocumentStatus;
use App\Jobs\GenerateBookEmbedding;
use App\Models\Book;
use App\Services\RagDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RagDocumentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Isolate content composition from the embedding job itself.
        Queue::fake();
    }

    public function test_it_composes_content_from_all_of_the_books_fields(): void
    {
        $book = Book::factory()->create([
            'title' => 'Clean Code',
            'author' => 'Robert C. Martin',
            'category' => 'Technical',
            'publisher' => 'Prentice Hall',
            'description' => 'A handbook of agile software craftsmanship.',
        ]);

        app(RagDocumentService::class)->syncForBook($book);

        $document = $book->ragDocuments()->first();
        $this->assertNotNull($document);
        $this->assertSame(
            "Title: Clean Code\n"
            ."Author: Robert C. Martin\n"
            ."Category: Technical\n"
            ."Publisher: Prentice Hall\n"
            .'Description: A handbook of agile software craftsmanship.',
            $document->content,
        );
        $this->assertSame(RagDocumentStatus::Pending, $document->status);
    }

    public function test_a_null_description_yields_a_shorter_document_not_an_error(): void
    {
        $book = Book::factory()->create([
            'title' => 'Untitled Notes',
            'author' => 'Anon',
            'category' => 'Poetry',
            'publisher' => 'Self',
            'description' => null,
        ]);

        app(RagDocumentService::class)->syncForBook($book);

        $document = $book->ragDocuments()->first();
        $this->assertNotNull($document);
        $this->assertSame(
            "Title: Untitled Notes\n"
            ."Author: Anon\n"
            ."Category: Poetry\n"
            .'Publisher: Self',
            $document->content,
        );
    }

    public function test_resyncing_reuses_the_same_document_and_redispatches(): void
    {
        $book = Book::factory()->create();
        $service = app(RagDocumentService::class);

        $service->syncForBook($book);
        $first = $book->ragDocuments()->first();
        $this->assertNotNull($first);

        $book->update(['title' => 'A Wholly New Title']);
        $service->syncForBook($book->fresh());

        // Still exactly one document (upsert, not a new row each time).
        $this->assertSame(1, $book->fresh()->ragDocuments()->count());
        $this->assertSame($first->id, $book->fresh()->ragDocuments()->first()?->id);

        Queue::assertPushed(GenerateBookEmbedding::class);
    }
}
