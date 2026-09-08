<?php

namespace Tests\Feature\Books;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BookSearchRankingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Relevance ranking (title/author weighted above description) relies on the
     * generated `search_vector` tsvector + ts_rank, which are Postgres-only (see
     * add_search_vector_to_books_table). The fast local SQLite driver uses an
     * unranked LIKE fallback, so this behavior is exercised for real on
     * phpunit.ci.xml — same skip-on-sqlite pattern as
     * BookInventoryCheckConstraintTest (spec 004).
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped(
                'Full-text relevance ranking is Postgres-only '
                .'(see add_search_vector_to_books_table); exercised on phpunit.ci.xml.'
            );
        }
    }

    public function test_a_title_match_ranks_above_a_description_only_match(): void
    {
        // Same term "algorithms" appears in one book's title and another's
        // description. Title is weight A, description is weight C, so the title
        // match must come back first.
        $descriptionMatch = Book::factory()->create([
            'title' => 'Introduction to Databases',
            'description' => 'A gentle tour of algorithms behind query planners.',
        ]);
        $titleMatch = Book::factory()->create([
            'title' => 'Algorithms Unlocked',
            'description' => 'A clear introduction to computing.',
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)->get('/books?query='.urlencode('algorithms'))
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($titleMatch, $descriptionMatch) {
                $ids = collect($page->toArray()['props']['books']['data'])
                    ->pluck('id')
                    ->all();

                $this->assertSame([$titleMatch->id, $descriptionMatch->id], $ids);
            });
    }
}
