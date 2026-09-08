<?php

namespace App\Services;

use App\AI\Contracts\EmbeddingServiceInterface;
use App\AI\Contracts\LLMServiceInterface;
use App\AI\Prompts\RecommendationPrompt;
use App\Exceptions\RecommendationFailedException;
use App\Models\Book;
use App\Models\RagDocument;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

class RecommendationService
{
    public function __construct(
        private readonly EmbeddingServiceInterface $embeddings,
        private readonly VectorSearchService $vectorSearch,
        private readonly LLMServiceInterface $llm,
        private readonly RecommendationPrompt $prompt,
    ) {}

    /**
     * Recommend catalog books for a free-text query, grounded entirely in the
     * retrieved catalog context (spec 008):
     *   embed query -> retrieve top-K completed documents -> build a grounded
     *   prompt -> ask the LLM -> parse its structured JSON -> attach LIVE
     *   availability from each Book (queried fresh, never trusted from the LLM).
     *
     * @return array{status: 'ok'|'no_matches', recommendations: array<int, array{
     *     id: int, title: string, author: string, why: string,
     *     available_copies: int, total_copies: int, is_available: bool
     * }>}
     */
    public function recommend(string $query, int $limit = 5): array
    {
        $embedding = $this->embeddings->embed($query);

        $documents = $this->vectorSearch->search($embedding, $limit);

        $candidates = $this->candidatesFromDocuments($documents);

        // Nothing relevant retrieved — don't waste an LLM call inventing books.
        if ($candidates === []) {
            return $this->emptyResult();
        }

        $parsed = $this->parse($this->generate($query, $candidates));

        return $this->buildResult($parsed, $candidates);
    }

    /**
     * Flatten the retrieved documents into a book-id-keyed candidate map. The
     * relationship is many-to-many, so a book could appear via more than one
     * document; the first document's content wins (dedupe by book id).
     *
     * @param  Collection<int, RagDocument>  $documents
     * @return array<int, array{title: string, author: string, content: string}>
     */
    private function candidatesFromDocuments(Collection $documents): array
    {
        $candidates = [];

        foreach ($documents as $document) {
            foreach ($document->books as $book) {
                $candidates[$book->id] ??= [
                    'title' => $book->title,
                    'author' => $book->author,
                    'content' => $document->content,
                ];
            }
        }

        return $candidates;
    }

    /**
     * Call the LLM, translating a slow/unreachable provider into the same
     * clean RecommendationFailedException the controller already maps to a
     * user-facing error — never a raw connection/HTTP exception leaking out
     * (spec 008 NFR: distinct, clear error when the provider is unreachable).
     *
     * @param  array<int, array{title: string, author: string, content: string}>  $candidates
     */
    private function generate(string $query, array $candidates): string
    {
        try {
            return $this->llm->generate($this->prompt->build($query, $candidates));
        } catch (ConnectionException|RequestException $e) {
            throw new RecommendationFailedException(
                'The recommendation service is currently unavailable. Please try again shortly.',
                previous: $e,
            );
        }
    }

    /**
     * Parse the LLM's structured JSON response into a list of book-id/reason
     * pairs. A response that isn't valid JSON of the expected shape is a clean
     * internal failure — never a raw parse exception leaking to the user.
     *
     * @return array<int, array{book_id: int, reason: string}>
     */
    private function parse(string $raw): array
    {
        $decoded = json_decode($this->stripFences($raw), true);

        if (! is_array($decoded) || ! isset($decoded['recommendations']) || ! is_array($decoded['recommendations'])) {
            throw new RecommendationFailedException;
        }

        $items = [];

        foreach ($decoded['recommendations'] as $item) {
            if (! is_array($item) || ! isset($item['book_id']) || ! is_numeric($item['book_id'])) {
                continue;
            }

            $items[] = [
                'book_id' => (int) $item['book_id'],
                'reason' => isset($item['reason']) && is_string($item['reason']) ? $item['reason'] : '',
            ];
        }

        return $items;
    }

    /**
     * Tolerate a model that wraps its JSON in a ```json ... ``` markdown fence
     * despite being asked not to.
     */
    private function stripFences(string $raw): string
    {
        $trimmed = trim($raw);

        if (str_starts_with($trimmed, '```')) {
            $trimmed = (string) preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $trimmed);
        }

        return trim($trimmed);
    }

    /**
     * Build the final result, attaching each recommended book's LIVE
     * availability from a fresh query. Anything the model referenced that
     * wasn't in the retrieved context (hallucinated id) or was deleted between
     * retrieval and now is dropped rather than shown as a broken recommendation.
     *
     * @param  array<int, array{book_id: int, reason: string}>  $parsed
     * @param  array<int, array{title: string, author: string, content: string}>  $candidates
     * @return array{status: 'ok'|'no_matches', recommendations: array<int, array{
     *     id: int, title: string, author: string, why: string,
     *     available_copies: int, total_copies: int, is_available: bool
     * }>}
     */
    private function buildResult(array $parsed, array $candidates): array
    {
        /** @var Collection<int, Book> $books */
        $books = Book::query()
            ->whereIn('id', array_column($parsed, 'book_id'))
            ->get()
            ->keyBy('id');

        $recommendations = [];

        foreach ($parsed as $item) {
            $id = $item['book_id'];

            // Not part of the retrieved context (hallucinated), or gone.
            if (! isset($candidates[$id])) {
                continue;
            }

            $book = $books->get($id);

            if ($book === null) {
                continue;
            }

            $recommendations[] = [
                'id' => $book->id,
                'title' => $book->title,
                'author' => $book->author,
                'why' => $item['reason'],
                'available_copies' => $book->available_copies,
                'total_copies' => $book->total_copies,
                'is_available' => $book->available_copies > 0,
            ];
        }

        return $recommendations === []
            ? $this->emptyResult()
            : ['status' => 'ok', 'recommendations' => $recommendations];
    }

    /**
     * @return array{status: 'no_matches', recommendations: array<int, never>}
     */
    private function emptyResult(): array
    {
        return ['status' => 'no_matches', 'recommendations' => []];
    }
}
