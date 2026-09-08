<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Deliberately does NOT use WithoutModelEvents: BookSeeder relies on
     * Book's `created` observer (spec 007) to compose each book's RAG
     * document and dispatch its embedding job. Suppressing model events here
     * would silently seed a catalog with zero embeddings on every fresh
     * `php artisan db:seed` — confirmed by reproducing it.
     */
    public function run(): void
    {
        $this->call(UserSeeder::class);
        $this->call(BookSeeder::class);
    }
}
