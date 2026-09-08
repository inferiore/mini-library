<?php

namespace Tests\Feature\Rag;

use App\Enums\RagDocumentStatus;
use App\Models\Book;
use App\Models\RagDocument;
use App\Services\VectorSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Real cosine-similarity ranking relies on pgvector's `<=>` operator and the
 * HNSW index, which exist only on Postgres (see create_rag_documents_table).
 * SQLite has no vector operator, so this behavior is exercised for real on
 * phpunit.ci.xml — same skip-on-sqlite pattern as BookSearchRankingTest.
 */
class VectorSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The book observer would otherwise dispatch real embedding jobs; we
        // control the fixture embeddings by hand here.
        Queue::fake();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped(
                'pgvector similarity search is Postgres-only '
                .'(see create_rag_documents_table); exercised on phpunit.ci.xml.'
            );
        }
    }

    public function test_it_returns_completed_documents_ordered_by_cosine_similarity(): void
    {
        $query = $this->vector([0 => 1.0]);

        // Distances to `query`: near = 0, mid ~ 0.29, far = 1.
        $near = RagDocument::factory()->completed($this->vector([0 => 1.0]))->create();
        $mid = RagDocument::factory()->completed($this->vector([0 => 1.0, 1 => 1.0]))->create();
        $far = RagDocument::factory()->completed($this->vector([1 => 1.0]))->create();

        // Nearest by direction, but not completed -> must be excluded.
        RagDocument::factory()->failed()->create([
            'embedding' => json_encode($this->vector([0 => 1.0])),
        ]);
        RagDocument::factory()->create([
            'status' => RagDocumentStatus::Pending,
            'embedding' => json_encode($this->vector([0 => 1.0])),
        ]);

        $results = app(VectorSearchService::class)->search($query, 5);

        $this->assertSame(
            [$near->id, $mid->id, $far->id],
            $results->pluck('id')->all(),
        );
    }

    public function test_it_eager_loads_the_related_book_and_respects_the_limit(): void
    {
        foreach (range(1, 3) as $i) {
            $book = Book::factory()->create();
            $document = RagDocument::factory()->completed($this->vector([0 => 1.0]))->create();
            $book->ragDocuments()->attach($document);
        }

        $results = app(VectorSearchService::class)->search($this->vector([0 => 1.0]), 2);

        $this->assertCount(2, $results);
        $this->assertTrue($results->first()?->relationLoaded('books'));
    }

    /**
     * A 1536-dimension vector (the frozen column width) with the given
     * components set; cosine distance ignores magnitude, so no normalization
     * is needed for ordering assertions.
     *
     * @param  array<int, float>  $components
     * @return array<int, float>
     */
    private function vector(array $components): array
    {
        $vector = array_fill(0, 1536, 0.0);

        foreach ($components as $index => $value) {
            $vector[$index] = $value;
        }

        return $vector;
    }
}
