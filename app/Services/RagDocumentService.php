<?php

namespace App\Services;

use App\Enums\RagDocumentStatus;
use App\Jobs\GenerateBookEmbedding;
use App\Models\Book;
use App\Models\RagDocument;
use Illuminate\Support\Facades\DB;

class RagDocumentService
{
    /**
     * (Re)build the book's single RAG document from its structured fields,
     * reset it to `pending`, and dispatch the async embedding job. Called from
     * the BookNeedsReembedding listener (spec 003's BookObserver fires the
     * event on create / embedding-relevant-field change).
     *
     * The document row + pivot are upserted atomically; the job is dispatched
     * only after that write commits so a worker can never pick it up before
     * the row exists.
     */
    public function syncForBook(Book $book): void
    {
        $content = $this->composeContent($book);

        $document = DB::transaction(function () use ($book, $content): RagDocument {
            $document = $book->ragDocuments()->first();

            if ($document === null) {
                $document = RagDocument::create([
                    'content' => $content,
                    'status' => RagDocumentStatus::Pending,
                ]);

                $book->ragDocuments()->attach($document);

                return $document;
            }

            // Existing document: rewrite content and wipe the prior embedding
            // result so a stale vector can never survive a content change.
            $document->forceFill([
                'content' => $content,
                'status' => RagDocumentStatus::Pending,
                'embedding' => null,
                'embedding_provider' => null,
                'embedding_model' => null,
                'error_message' => null,
                'attempts' => 0,
                'processed_at' => null,
            ])->save();

            return $document;
        });

        GenerateBookEmbedding::dispatch($document->id);
    }

    /**
     * One semantically coherent document per book, composed from its
     * structured fields. Title and author are always present; category,
     * publisher and description are omitted when empty, so a book with a
     * null/empty description still yields a valid (shorter) document rather
     * than an error (spec 007 edge case).
     */
    private function composeContent(Book $book): string
    {
        $lines = [
            "Title: {$book->title}",
            "Author: {$book->author}",
        ];

        if (filled($book->category)) {
            $lines[] = "Category: {$book->category}";
        }

        if (filled($book->publisher)) {
            $lines[] = "Publisher: {$book->publisher}";
        }

        if (filled($book->description)) {
            $lines[] = "Description: {$book->description}";
        }

        return implode("\n", $lines);
    }
}
