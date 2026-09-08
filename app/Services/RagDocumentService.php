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
     * Reset a single document back to `pending` and re-dispatch its embedding
     * job (spec 009's Retry/Regenerate). The reset + guard is a single atomic
     * `UPDATE ... WHERE status != 'processing'` (the spec 004/005 guarded-write
     * pattern, not a read-then-write): if a background worker has already
     * flipped the row to `processing` between the admin's read and this write,
     * the update affects 0 rows and we skip the dispatch — so a genuinely
     * in-flight job can never be duplicated.
     *
     * Returns true when the document was requeued, false when it was skipped
     * because it was already `processing`.
     */
    public function requeue(RagDocument $document): bool
    {
        $affected = DB::table('rag_documents')
            ->where('id', $document->id)
            ->where('status', '!=', RagDocumentStatus::Processing->value)
            ->update([
                'status' => RagDocumentStatus::Pending->value,
                'attempts' => 0,
                'error_message' => null,
                'embedding' => null,
                'embedding_provider' => null,
                'embedding_model' => null,
                'processed_at' => null,
                'updated_at' => now(),
            ]);

        if ($affected === 0) {
            return false;
        }

        GenerateBookEmbedding::dispatch($document->id);

        return true;
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
