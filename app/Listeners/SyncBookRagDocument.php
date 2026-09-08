<?php

namespace App\Listeners;

use App\Events\BookNeedsReembedding;
use App\Services\RagDocumentService;

/**
 * Bridges spec 003's BookNeedsReembedding event to spec 007's RAG pipeline:
 * whenever a book is created or an embedding-relevant field changes, rebuild
 * its RAG document and (re)dispatch the embedding job. Registered in
 * AppServiceProvider (same place the BookObserver is wired).
 */
class SyncBookRagDocument
{
    public function __construct(private readonly RagDocumentService $ragDocuments) {}

    public function handle(BookNeedsReembedding $event): void
    {
        $this->ragDocuments->syncForBook($event->book);
    }
}
