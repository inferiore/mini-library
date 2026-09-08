<?php

namespace Database\Seeders;

use App\Models\Book;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class BookSeeder extends Seeder
{
    /**
     * Populate the catalog from a real, internet-sourced book list (title/
     * author/isbn/description/published_year/category/publisher — see
     * database/data/real_books.json and the `books:import` command) rather
     * than factory-generated fake data. Safe to re-run.
     */
    public function run(): void
    {
        if (Book::query()->exists()) {
            return;
        }

        Artisan::call('books:import', [
            'path' => database_path('data/real_books.json'),
        ]);

        $this->command->info(trim(Artisan::output()));
    }
}
