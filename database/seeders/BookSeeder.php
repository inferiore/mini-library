<?php

namespace Database\Seeders;

use App\Models\Book;
use Illuminate\Database\Seeder;

class BookSeeder extends Seeder
{
    /**
     * Populate a demo catalog spanning categories and availability states,
     * so search (006), recommendations (008), and checkout (005) are
     * demonstrable without manual data entry. Safe to re-run.
     */
    public function run(): void
    {
        if (Book::query()->exists()) {
            return;
        }

        Book::factory()->count(20)->create();
        Book::factory()->count(15)->partiallyBorrowed()->create();
        Book::factory()->count(5)->fullyBorrowed()->create();
    }
}
