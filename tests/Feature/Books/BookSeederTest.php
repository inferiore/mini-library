<?php

namespace Tests\Feature\Books;

use App\Models\Book;
use Database\Seeders\BookSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_populates_a_catalog_spanning_categories_and_availability_states(): void
    {
        $this->seed(BookSeeder::class);

        $this->assertTrue(Book::query()->count() >= 30);
        $this->assertTrue(Book::query()->distinct()->count('category') > 1);
        $this->assertTrue(Book::query()->where('available_copies', 0)->exists());
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
