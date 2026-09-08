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
     * Laravel's schema builder has no portable way to express a CHECK
     * constraint, so the column + constraint are added via a single raw
     * statement. The same SQL works unmodified on both SQLite and Postgres.
     */
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE users ADD COLUMN role VARCHAR(255) NOT NULL DEFAULT 'member' ".
            "CHECK (role IN ('admin', 'librarian', 'member'))"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
