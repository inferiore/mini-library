<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One embeddable text document per book (see docs/specs/007-rag.md). The
     * `embedding` column is the only per-driver bit: on Postgres it's a real
     * pgvector `vector(1536)` column created via raw SQL (Laravel's schema
     * builder has no native pgvector type) plus an HNSW cosine index; on the
     * fast local SQLite driver it's a plain nullable `text` column holding a
     * JSON-encoded array — enough to exercise the job/status state machine
     * without real similarity search (which is covered on phpunit.ci.xml).
     *
     * The column width (1536) is a frozen migration artifact, deliberately NOT
     * read from config/env: two developers with different VECTOR_DIM values
     * must not produce different schemas. Changing the provider's output width
     * requires a new migration (see the dimension-change runbook in spec 007).
     */
    public function up(): void
    {
        Schema::create('rag_documents', function (Blueprint $table) {
            $table->id();
            $table->text('content');

            // Postgres gets its real vector column added below; SQLite keeps
            // this plain nullable text column.
            if (DB::connection()->getDriverName() !== 'pgsql') {
                $table->text('embedding')->nullable();
            }

            $table->string('status')->default('pending')->index();
            $table->string('embedding_provider')->nullable();
            $table->string('embedding_model')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE rag_documents ADD COLUMN embedding vector(1536)');

            // HNSW over cosine distance (vector_cosine_ops), chosen over
            // IVFFlat because it needs no list-count tuning as the catalog
            // grows (spec 007 NFR).
            DB::statement(
                'CREATE INDEX rag_documents_embedding_hnsw ON rag_documents '.
                'USING hnsw (embedding vector_cosine_ops)'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rag_documents');
    }
};
