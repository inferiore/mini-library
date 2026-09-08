<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title')->index();
            $table->string('author')->index();
            $table->string('isbn', 20)->nullable()->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('published_year')->nullable();
            $table->string('category')->nullable()->index();
            $table->string('publisher')->nullable();
            $table->string('cover_path')->nullable();
            $table->unsignedInteger('total_copies');
            $table->unsignedInteger('available_copies');
            $table->softDeletes();
            $table->timestamps();
        });

        // Unlike users.role (spec 001), this CHECK can't use the "ADD COLUMN
        // ... CHECK(...)" trick, because it spans two columns created
        // together in this same CREATE TABLE — and SQLite has no
        // "ALTER TABLE ... ADD CONSTRAINT" at all (confirmed: SQLite 3.51
        // rejects it with a syntax error; only Postgres supports adding a
        // constraint after the fact). So this constraint is Postgres-only,
        // enforced as defense-in-depth alongside BookService's own
        // application-level validation, which is what SQLite (the fast
        // local/test driver) relies on instead. Covered for real on
        // `phpunit.ci.xml`, matching this project's established pattern for
        // Postgres-only guarantees (see spec 001/007).
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE books ADD CONSTRAINT books_available_copies_check '.
                'CHECK (available_copies >= 0 AND available_copies <= total_copies)'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
