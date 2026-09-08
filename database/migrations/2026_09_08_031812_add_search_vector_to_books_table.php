<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Postgres-only: a generated `tsvector` column combining the searchable
     * text fields with relevance weights (title/author = A, category/publisher
     * = B, description = C), plus a GIN index for fast full-text lookups. Using
     * a GENERATED ALWAYS ... STORED column (Postgres 12+) rather than a trigger
     * means the vector can never drift from its source columns.
     *
     * On SQLite (the fast local/test driver) this is skipped entirely — the
     * search query falls back to LIKE matching across the same field set, and
     * the ranking behavior is validated for real on `phpunit.ci.xml`. Same
     * per-driver guard pattern established by the books CHECK constraint
     * (see create_books_table) and spec 004.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE books ADD COLUMN search_vector tsvector
            GENERATED ALWAYS AS (
                setweight(to_tsvector('english', coalesce(title, '')), 'A') ||
                setweight(to_tsvector('english', coalesce(author, '')), 'A') ||
                setweight(to_tsvector('english', coalesce(category, '')), 'B') ||
                setweight(to_tsvector('english', coalesce(publisher, '')), 'B') ||
                setweight(to_tsvector('english', coalesce(description, '')), 'C')
            ) STORED
        SQL);

        DB::statement('CREATE INDEX books_search_vector_gin ON books USING GIN (search_vector)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS books_search_vector_gin');
        DB::statement('ALTER TABLE books DROP COLUMN IF EXISTS search_vector');
    }
};
