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
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            // restrictOnDelete: loan history must survive; a book/user with
            // loans can't be hard-deleted out from under them. (Books use
            // soft deletes anyway — spec 003.)
            $table->foreignId('book_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('checked_out_at');
            $table->timestamp('due_at');
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();

            $table->index('book_id');
            $table->index('user_id');
        });

        // A member may hold at most one *active* (unreturned) loan of a given
        // book, but any number of returned historical loans for it — so the
        // uniqueness is partial (WHERE returned_at IS NULL). Laravel's schema
        // builder has no cross-driver partial-index API, so emit the raw
        // statement per driver. Both Postgres and SQLite (3.8+) support the
        // same `CREATE UNIQUE INDEX ... WHERE ...` syntax, so one statement
        // covers both of this project's drivers; guarded anyway so an
        // unexpected driver fails loudly rather than silently skipping the
        // constraint. (Mirrors spec 003/004's per-driver migration pattern.)
        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement(
                'CREATE UNIQUE INDEX loans_active_book_user_unique '.
                'ON loans (book_id, user_id) WHERE returned_at IS NULL'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
