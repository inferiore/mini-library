<?php

namespace Tests\Feature\Books;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookInventoryCheckConstraintTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Defense-in-depth: even bypassing the application entirely, the DB itself
     * must reject an inventory row that violates the invariant. The constraint
     * spans two columns created together and is therefore Postgres-only (see
     * the create_books_table migration) — covered for real on phpunit.ci.xml,
     * skipped on the fast local SQLite driver which relies on the
     * application-layer guard instead.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped(
                'The books available_copies CHECK constraint is Postgres-only '
                .'(see create_books_table migration); exercised on phpunit.ci.xml.'
            );
        }
    }

    public function test_a_raw_insert_with_available_greater_than_total_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        DB::table('books')->insert($this->row(['total_copies' => 3, 'available_copies' => 4]));
    }

    public function test_a_raw_insert_with_negative_available_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        DB::table('books')->insert($this->row(['total_copies' => 3, 'available_copies' => -1]));
    }

    public function test_a_raw_update_that_would_drive_available_negative_is_rejected(): void
    {
        $id = DB::table('books')->insertGetId(
            $this->row(['total_copies' => 3, 'available_copies' => 1])
        );

        $this->expectException(QueryException::class);

        DB::table('books')->where('id', $id)->update([
            'available_copies' => DB::raw('available_copies - 5'),
        ]);
    }

    public function test_a_valid_raw_write_is_accepted(): void
    {
        $id = DB::table('books')->insertGetId(
            $this->row(['total_copies' => 5, 'available_copies' => 0])
        );

        DB::table('books')->where('id', $id)->update([
            'available_copies' => 5,
        ]);

        $this->assertSame(5, (int) DB::table('books')->where('id', $id)->value('available_copies'));
    }

    /**
     * @param  array<string, int>  $overrides
     * @return array<string, mixed>
     */
    private function row(array $overrides): array
    {
        return array_merge([
            'title' => 'Raw Insert Book',
            'author' => 'Nobody',
            'total_copies' => 1,
            'available_copies' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
    }
}
