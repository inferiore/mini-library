<?php

namespace Tests\Feature\Books;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BookSearchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, int>  $expectedIds
     */
    private function assertResultIds(array $expectedIds, string $queryString): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get("/books?{$queryString}")
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($expectedIds) {
                $page->component('books/index');

                $returned = collect($page->toArray()['props']['books']['data'])
                    ->pluck('id')
                    ->sort()
                    ->values()
                    ->all();

                sort($expectedIds);

                $this->assertSame($expectedIds, $returned);
            });
    }

    public function test_search_by_exact_title_substring_returns_matching_book(): void
    {
        $match = Book::factory()->create(['title' => 'The Pragmatic Programmer']);
        Book::factory()->create(['title' => 'Clean Code', 'description' => 'A handbook.']);

        $this->assertResultIds([$match->id], 'query='.urlencode('Pragmatic'));
    }

    public function test_search_by_author_name_returns_matching_book(): void
    {
        $match = Book::factory()->create(['author' => 'Andersonhunt Xavier']);
        Book::factory()->create(['author' => 'Robert Martin', 'description' => 'A book.']);

        $this->assertResultIds([$match->id], 'query='.urlencode('Andersonhunt'));
    }

    public function test_search_by_full_and_partial_isbn_returns_matching_book(): void
    {
        $match = Book::factory()->create(['isbn' => '9780132350884']);
        Book::factory()->create(['isbn' => '9781119999999']);

        // Full ISBN.
        $this->assertResultIds([$match->id], 'query='.urlencode('9780132350884'));
        // Partial ISBN — wouldn't tokenize as full-text, matched via LIKE path.
        $this->assertResultIds([$match->id], 'query='.urlencode('013235088'));
    }

    public function test_search_by_category_filter_alone_and_combined_with_query(): void
    {
        $fantasyDragons = Book::factory()->create([
            'title' => 'Firewyrm Saga',
            'category' => 'Fantasy',
        ]);
        $fantasyElves = Book::factory()->create([
            'title' => 'Woodland Kin',
            'category' => 'Fantasy',
            'description' => 'A tale of the forest.',
        ]);
        Book::factory()->create([
            'title' => 'Firewyrm Physics',
            'category' => 'Science',
        ]);

        // Category filter alone returns both Fantasy books.
        $this->assertResultIds(
            [$fantasyDragons->id, $fantasyElves->id],
            'category='.urlencode('Fantasy')
        );

        // Combined with a text query narrows to the matching Fantasy book only.
        $this->assertResultIds(
            [$fantasyDragons->id],
            'query='.urlencode('Firewyrm').'&category='.urlencode('Fantasy')
        );
    }

    public function test_search_by_publisher_filter(): void
    {
        $match = Book::factory()->create(['publisher' => 'Addison-Wesley']);
        Book::factory()->create(['publisher' => 'No Starch Press']);

        $this->assertResultIds([$match->id], 'publisher='.urlencode('Addison-Wesley'));
    }

    public function test_empty_and_whitespace_query_returns_full_listing(): void
    {
        Book::factory()->count(3)->create();

        $allIds = Book::query()->pluck('id')->all();

        $this->assertResultIds($allIds, '');
        $this->assertResultIds($allIds, 'query=');
        $this->assertResultIds($allIds, 'query='.urlencode('   '));
    }

    public function test_special_characters_in_query_do_not_cause_a_500(): void
    {
        Book::factory()->create(['title' => 'Cats & Dogs']);

        $user = User::factory()->create();

        foreach (['&', ':', "'", 'C++ & Rust', 'a:b', "O'Reilly", '50% off', 'a_b'] as $term) {
            $this->actingAs($user)
                ->get('/books?query='.urlencode($term))
                ->assertOk();
        }
    }

    public function test_query_over_max_length_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/books?query='.urlencode(str_repeat('x', 256)))
            ->assertSessionHasErrors('query');
    }

    public function test_unmatched_filter_value_returns_empty_not_error(): void
    {
        Book::factory()->create(['category' => 'Fiction']);

        $this->assertResultIds([], 'category='.urlencode('Nonexistent Category'));
    }
}
