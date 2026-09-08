<?php

namespace Tests\Feature\Recommendations;

use App\AI\Contracts\EmbeddingServiceInterface;
use App\AI\Contracts\LLMServiceInterface;
use App\Models\Book;
use App\Models\RagDocument;
use App\Models\User;
use App\Services\VectorSearchService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Spec 008 recommendations. VectorSearchService's real cosine ranking is
 * Postgres-only (spec 007), so every test here mocks it — the fast SQLite
 * suite controls exactly which retrieved documents the service sees, while
 * the already-bound FakeEmbeddingService / FakeLLMService stand in for the
 * AI providers so no test ever hits a real API.
 */
class RecommendationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a real, book-linked completed document collection for the mocked
     * VectorSearchService to return.
     *
     * @param  array<int, Book>  $books
     * @return Collection<int, RagDocument>
     */
    private function documentsFor(array $books): Collection
    {
        foreach ($books as $book) {
            $document = RagDocument::factory()->completed()->create([
                'content' => "Catalog notes about {$book->title}.",
            ]);
            $book->ragDocuments()->attach($document);
        }

        return RagDocument::with('books')->get();
    }

    /**
     * @param  Collection<int, RagDocument>  $documents
     */
    private function mockVectorSearch(Collection $documents): void
    {
        $this->mock(VectorSearchService::class, function (MockInterface $mock) use ($documents): void {
            $mock->shouldReceive('search')->andReturn($documents);
        });
    }

    public function test_a_matching_query_returns_those_books_with_llm_explanations(): void
    {
        $user = User::factory()->create();
        $architecture = Book::factory()->create(['title' => 'Clean Architecture', 'author' => 'Robert C. Martin']);
        $pragmatic = Book::factory()->create(['title' => 'The Pragmatic Programmer', 'author' => 'Hunt & Thomas']);

        $this->mockVectorSearch($this->documentsFor([$architecture, $pragmatic]));

        $response = $this->actingAs($user)
            ->postJson('/recommendations', ['query' => 'practical software architecture']);

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');

        $returned = collect($response->json('recommendations'));

        $this->assertEqualsCanonicalizing(
            [$architecture->id, $pragmatic->id],
            $returned->pluck('id')->all(),
        );

        // The explanation is the (fake) LLM's, grounded in the book's title.
        $architectureRec = $returned->firstWhere('id', $architecture->id);
        $this->assertStringContainsString('Clean Architecture', $architectureRec['why']);
    }

    public function test_availability_reflects_live_book_state_not_the_llm_output(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['total_copies' => 5, 'available_copies' => 2]);

        $this->mockVectorSearch($this->documentsFor([$book]));

        $response = $this->actingAs($user)
            ->postJson('/recommendations', ['query' => 'anything relevant']);

        $response->assertOk();
        $response->assertJsonPath('recommendations.0.available_copies', 2);
        $response->assertJsonPath('recommendations.0.total_copies', 5);
        $response->assertJsonPath('recommendations.0.is_available', true);
    }

    public function test_a_query_with_no_relevant_documents_returns_no_matches(): void
    {
        $user = User::factory()->create();

        /** @var Collection<int, RagDocument> $empty */
        $empty = RagDocument::query()->whereRaw('1 = 0')->get();
        $this->mockVectorSearch($empty);

        // The LLM must never be asked to invent something when nothing matched.
        $this->mock(LLMServiceInterface::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('generate');
        });

        $response = $this->actingAs($user)
            ->postJson('/recommendations', ['query' => 'no such catalog subject exists']);

        $response->assertOk();
        $response->assertJsonPath('status', 'no_matches');
        $response->assertJsonCount(0, 'recommendations');
    }

    public function test_an_unauthenticated_request_is_rejected(): void
    {
        $this->postJson('/recommendations', ['query' => 'something to read'])
            ->assertUnauthorized();
    }

    public function test_exceeding_the_rate_limit_returns_a_clear_throttled_message(): void
    {
        $user = User::factory()->create();

        /** @var Collection<int, RagDocument> $empty */
        $empty = RagDocument::query()->whereRaw('1 = 0')->get();
        $this->mockVectorSearch($empty);

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)
                ->postJson('/recommendations', ['query' => 'a valid query'])
                ->assertOk();
        }

        $this->actingAs($user)
            ->postJson('/recommendations', ['query' => 'a valid query'])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Too many recommendation requests. Please wait a moment and try again.');
    }

    public function test_a_malformed_llm_response_is_handled_gracefully(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->mockVectorSearch($this->documentsFor([$book]));

        $this->mock(LLMServiceInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generate')->andReturn('this is not valid JSON at all');
        });

        $response = $this->actingAs($user)
            ->postJson('/recommendations', ['query' => 'a valid query']);

        $response->assertStatus(502);
        // A clean, generic message with no leaked parse/exception detail.
        $response->assertExactJson([
            'message' => 'We could not generate recommendations right now. Please try again.',
        ]);
    }

    public function test_an_unreachable_llm_provider_returns_a_clean_distinct_error(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->mockVectorSearch($this->documentsFor([$book]));

        $this->mock(LLMServiceInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generate')->andThrow(
                new ConnectionException('cURL error 28: Operation timed out')
            );
        });

        $response = $this->actingAs($user)
            ->postJson('/recommendations', ['query' => 'a valid query']);

        $response->assertStatus(502);
        // Distinct from both the malformed-response message and the no-matches
        // (200) case, and with no leaked connection/HTTP exception detail.
        $response->assertExactJson([
            'message' => 'The recommendation service is currently unavailable. Please try again shortly.',
        ]);
    }

    public function test_validation_rejects_bad_queries_before_any_service_call(): void
    {
        $user = User::factory()->create();

        // If validation is skipped, these fakes would be hit — assert they aren't.
        $this->mock(EmbeddingServiceInterface::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('embed');
        });
        $this->mock(VectorSearchService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('search');
        });

        foreach (['', '  ', 'ab', str_repeat('x', 501)] as $query) {
            $this->actingAs($user)
                ->postJson('/recommendations', ['query' => $query])
                ->assertStatus(422)
                ->assertJsonValidationErrors('query');
        }
    }
}
