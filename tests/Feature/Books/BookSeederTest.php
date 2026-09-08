<?php

namespace Tests\Feature\Books;

use App\Models\Book;
use Database\Seeders\BookSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_populates_a_catalog_spanning_multiple_categories(): void
    {
        $this->seed(BookSeeder::class);

        // Real, internet-sourced titles (database/data/real_books.json) —
        // not factory-generated fake data. Availability states aren't baked
        // into the seed; they come from real checkouts (spec 005) instead.
        $this->assertTrue(Book::query()->count() >= 15);
        $this->assertTrue(Book::query()->distinct()->count('category') > 1);
        $this->assertTrue(
            Book::query()->whereColumn('available_copies', '=', 'total_copies')->exists()
        );
    }

    public function test_it_is_safe_to_run_twice(): void
    {
        $this->seed(BookSeeder::class);
        $countAfterFirstRun = Book::query()->count();

        $this->seed(BookSeeder::class);

        $this->assertSame($countAfterFirstRun, Book::query()->count());
    }
}
