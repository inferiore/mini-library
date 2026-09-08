<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Many-to-many pivot between books and rag_documents. Today the
     * cardinality is 1:1 (one document per book), but modeling it as a pivot
     * lets multiple documents per book be added later (reviews, excerpts)
     * without a schema change (spec 007 FR1). Both FKs cascade on delete, so
     * deleting a book cleans up its pivot rows.
     */
    public function up(): void
    {
        Schema::create('book_rag_document', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rag_document_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['book_id', 'rag_document_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_rag_document');
    }
};
