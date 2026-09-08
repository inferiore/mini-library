<?php

namespace App\Jobs;

use App\AI\Contracts\EmbeddingServiceInterface;
use App\Enums\RagDocumentStatus;
use App\Models\RagDocument;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Generates (or regenerates) the embedding vector for a single rag_documents
 * row. Constructed with the document's id — never the model instance — so a
 * job sitting on the queue can't act on a stale serialized snapshot of the
 * row's content/status (docs/specs/007-rag.md).
 */
class GenerateBookEmbedding implements ShouldQueue
{
    use Queueable;

    /**
     * Retry transient failures (timeouts, 5xx) a few times before landing on
     * `failed`; a permanent failure (e.g. a 401) still exhausts these and then
     * fails cleanly rather than retrying forever.
     */
    public int $tries = 3;

    public function __construct(public int $ragDocumentId) {}

    /**
     * Increasing backoff (seconds) between retries, so a briefly-unavailable
     * provider gets time to recover.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(EmbeddingServiceInterface $embeddings): void
    {
        $document = RagDocument::find($this->ragDocumentId);

        // The book (and its document) may have been deleted between dispatch
        // and execution — nothing to embed, not an error.
        if ($document === null) {
            return;
        }

        $document->forceFill([
            'status' => RagDocumentStatus::Processing,
            'attempts' => $document->attempts + 1,
        ])->save();

        // A throw here propagates so Laravel's retry/backoff can re-attempt;
        // only once $tries is exhausted does failed() persist status=failed.
        $vector = $embeddings->embed($document->content);

        $document->forceFill([
            'embedding' => json_encode(array_values($vector)),
            'embedding_provider' => parse_url((string) config('ai.base_url'), PHP_URL_HOST) ?: null,
            'embedding_model' => config('ai.embedding_model'),
            'status' => RagDocumentStatus::Completed,
            'error_message' => null,
            'processed_at' => now(),
        ])->save();
    }

    /**
     * Called once the job has exhausted all retries. Persists the terminal
     * failed state with the captured error so spec 009's admin UI can surface
     * it (and offer a retry).
     */
    public function failed(?Throwable $exception): void
    {
        RagDocument::query()
            ->whereKey($this->ragDocumentId)
            ->update([
                'status' => RagDocumentStatus::Failed->value,
                'error_message' => $exception?->getMessage(),
                'updated_at' => now(),
            ]);
    }
}
