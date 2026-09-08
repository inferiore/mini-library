<?php

namespace Tests\Feature\Rag;

use App\AI\Contracts\EmbeddingServiceInterface;
use App\AI\Fakes\FakeEmbeddingService;
use App\Enums\RagDocumentStatus;
use App\Jobs\GenerateBookEmbedding;
use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BookEmbeddingPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_book_dispatches_the_embedding_job(): void
    {
        Queue::fake();

        $book = Book::factory()->create();

        $document = $book->ragDocuments()->first();
        $this->assertNotNull($document);
        $this->assertSame(RagDocumentStatus::Pending, $document->status);

        Queue::assertPushed(
            GenerateBookEmbedding::class,
            fn (GenerateBookEmbedding $job): bool => $job->ragDocumentId === $document->id,
        );
    }

    public function test_creating_a_book_produces_a_completed_embedding(): void
    {
        // Default test queue is `sync`, so the dispatched job runs inline and
        // drives the status through to completed via the fake embedding service.
        $book = Book::factory()->create();

        $document = $book->ragDocuments()->first();
        $this->assertNotNull($document);
        $this->assertSame(RagDocumentStatus::Completed, $document->status);
        $this->assertNotNull($document->embedding);
        $this->assertNotNull($document->processed_at);
        $this->assertNull($document->error_message);
    }

    public function test_editing_description_redispatches_but_editing_total_copies_does_not(): void
    {
        Queue::fake();

        $book = Book::factory()->create();
        Queue::assertPushed(GenerateBookEmbedding::class, 1);

        // An embedding-relevant field changed -> pipeline re-runs.
        $book->update(['description' => 'A freshly rewritten summary.']);
        Queue::assertPushed(GenerateBookEmbedding::class, 2);

        // total_copies is not embedding-relevant (spec 004 inventory) -> no re-run.
        $book->update(['total_copies' => $book->total_copies + 3]);
        Queue::assertPushed(GenerateBookEmbedding::class, 2);
    }

    public function test_the_embedding_service_is_the_deterministic_fake_during_tests(): void
    {
        // Enforces spec 007's hard constraint: no automated test may ever hit
        // the real Gemini API — Tests\TestCase rebinds the interface to the fake.
        $this->assertInstanceOf(
            FakeEmbeddingService::class,
            $this->app->make(EmbeddingServiceInterface::class),
        );
    }
}
