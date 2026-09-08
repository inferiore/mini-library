<?php

namespace App\Console\Commands;

use App\Models\Book;
use App\Services\BookService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ImportBooks extends Command
{
    protected $signature = 'books:import
        {path : Path to a JSON file containing an array of book objects}
        {--copies=3 : total_copies to set for each imported book}';

    protected $description = 'Import books from a JSON file (title/author/isbn/description/published_year/category/publisher), going through BookService::create() so validation, available_copies, and the RAG embedding pipeline all fire exactly as they would from the UI';

    public function handle(BookService $books): int
    {
        $path = $this->argument('path');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $rows = json_decode((string) file_get_contents($path), true);

        if (! is_array($rows)) {
            $this->error('Expected a JSON array of book objects.');

            return self::FAILURE;
        }

        $defaultCopies = max(1, (int) $this->option('copies'));

        $created = 0;
        $skipped = 0;

        foreach ($rows as $i => $row) {
            if (! is_array($row) || empty($row['title']) || empty($row['author'])) {
                $this->warn("Row {$i}: missing required title/author, skipped.");
                $skipped++;

                continue;
            }

            $isbn = isset($row['isbn']) ? (string) $row['isbn'] : null;

            if ($isbn !== null && Book::withTrashed()->where('isbn', $isbn)->exists()) {
                $this->line("Skipping \"{$row['title']}\" — ISBN {$isbn} already in the catalog.");
                $skipped++;

                continue;
            }

            $attributes = [
                'title' => (string) $row['title'],
                'author' => (string) $row['author'],
                'isbn' => $isbn,
                'description' => isset($row['description']) ? (string) $row['description'] : null,
                'published_year' => isset($row['published_year']) ? (int) $row['published_year'] : null,
                'category' => isset($row['category']) ? (string) $row['category'] : null,
                'publisher' => isset($row['publisher']) ? (string) $row['publisher'] : null,
                'total_copies' => isset($row['total_copies']) ? max(1, (int) $row['total_copies']) : $defaultCopies,
            ];

            $validator = Validator::make($attributes, [
                'title' => ['required', 'string', 'max:255'],
                'author' => ['required', 'string', 'max:255'],
                'isbn' => ['nullable', 'string', 'max:20'],
                'description' => ['nullable', 'string'],
                'published_year' => ['nullable', 'integer'],
                'category' => ['nullable', 'string', 'max:255'],
                'publisher' => ['nullable', 'string', 'max:255'],
                'total_copies' => ['required', 'integer', 'min:1'],
            ]);

            if ($validator->fails()) {
                $this->warn("Row {$i} (\"{$row['title']}\"): ".$validator->errors()->first());
                $skipped++;

                continue;
            }

            $books->create($validator->validated());
            $created++;
            $this->line("Created \"{$attributes['title']}\" by {$attributes['author']}.");
        }

        $this->newLine();
        $this->info("{$created} book(s) created, {$skipped} skipped.");

        if ($created > 0) {
            $pronoun = $created === 1 ? 'it' : 'them';
            $this->comment(Str::plural('Embedding job', $created).' dispatched to the queue — run `docker compose logs queue` or check the admin embeddings page to watch '.$pronoun.' complete.');
        }

        return self::SUCCESS;
    }
}
