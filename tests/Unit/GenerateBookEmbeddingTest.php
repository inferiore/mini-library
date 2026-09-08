<?php

namespace Tests\Unit;

use App\AI\Contracts\EmbeddingServiceInterface;
use App\Enums\RagDocumentStatus;
use App\Jobs\GenerateBookEmbedding;
use App\Models\RagDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class GenerateBookEmbeddingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_successful_run_stores_the_vector_and_completes(): void
    {
        $document = RagDocument::factory()->create([
            'content' => 'Title: A Book',
            'status' => RagDocumentStatus::Pending,
        ]);

        (new GenerateBookEmbedding($document->id))
            ->handle($this->app->make(EmbeddingServiceInterface::class));

        $document->refresh();
        $this->assertSame(RagDocumentStatus::Completed, $document->status);
        $this->assertNotNull($document->embedding);
        $this->assertSame(1, $document->attempts);
        $this->assertNotNull($document->processed_at);
    }

    public function test_it_marks_the_document_failed_with_the_error_after_retries_are_exhausted(): void
    {
        $document = RagDocument::factory()->create([
            'status' => RagDocumentStatus::Pending,
        ]);

        $throwing = new class implements EmbeddingServiceInterface
        {
            /** @return array<int, float> */
            public function embed(string $text): array
            {
                throw new RuntimeException('provider returned 500');
            }

            /**
             * @param  array<int, string>  $texts
             * @return array<int, array<int, float>>
             */
            public function embedMany(array $texts): array
            {
                throw new RuntimeException('provider returned 500');
            }
        };

        $job = new GenerateBookEmbedding($document->id);

        // handle() marks the row processing, counts the attempt, then lets the
        // provider error propagate so Laravel can retry it.
        try {
            $job->handle($throwing);
            $this->fail('Expected the embedding failure to propagate for retry.');
        } catch (RuntimeException $e) {
            $this->assertSame('provider returned 500', $e->getMessage());
        }

        $document->refresh();
        $this->assertSame(RagDocumentStatus::Processing, $document->status);
        $this->assertSame(1, $document->attempts);

        // Once $tries is exhausted, Laravel invokes failed() with the exception.
        $job->failed(new RuntimeException('provider returned 500'));

        $document->refresh();
        $this->assertSame(RagDocumentStatus::Failed, $document->status);
        $this->assertSame('provider returned 500', $document->error_message);
    }
}
