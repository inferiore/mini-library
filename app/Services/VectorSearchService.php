<?php

namespace App\Services;

use App\Models\RagDocument;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VectorSearchService
{
    /**
     * Return the `$limit` nearest completed documents to `$queryEmbedding` by
     * cosine distance, eager-loading each result's book(s). Only `completed`
     * documents are considered — a stale/failed/in-flight document is never
     * surfaced (spec 007 FR6).
     *
     * This is Postgres + pgvector only: cosine distance uses pgvector's `<=>`
     * operator against the HNSW index. SQLite has no vector operator, so the
     * fast local suite doesn't exercise ranking here (it's covered on
     * phpunit.ci.xml against real Postgres); calling this on any other driver
     * fails loudly rather than silently returning wrong results.
     *
     * @param  array<int, float>  $queryEmbedding
     * @return Collection<int, RagDocument>
     */
    public function search(array $queryEmbedding, int $limit = 5): Collection
    {
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'pgsql') {
            throw new RuntimeException(
                "Vector similarity search requires PostgreSQL + pgvector; not supported on the [{$driver}] driver."
            );
        }

        $vector = json_encode(array_values($queryEmbedding));

        return RagDocument::query()
            ->completed()
            ->with('books')
            ->orderByRaw('embedding <=> ?', [$vector])
            ->limit($limit)
            ->get();
    }
}
