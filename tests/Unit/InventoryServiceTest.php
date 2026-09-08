<?php

namespace Tests\Unit;

use App\Exceptions\InsufficientAvailableCopiesException;
use App\Models\Book;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InventoryService;
    }

    public function test_it_throws_carrying_the_on_loan_count_when_a_decrease_would_go_negative(): void
    {
        // 5 total, 2 available => 3 on loan.
        $book = Book::factory()->create(['total_copies' => 5, 'available_copies' => 2]);

        try {
            $this->service->adjustTotalCopies($book, 2);
            $this->fail('Expected InsufficientAvailableCopiesException.');
        } catch (InsufficientAvailableCopiesException $e) {
            $this->assertSame(3, $e->copiesOnLoan);
        }

        // No partial write.
        $fresh = $book->fresh();
        $this->assertSame(5, $fresh->total_copies);
        $this->assertSame(2, $fresh->available_copies);
    }

    public function test_a_losing_concurrent_decrease_is_rejected_and_the_invariant_holds(): void
    {
        // 5 total, 3 available (2 on loan).
        $id = Book::factory()->create(['total_copies' => 5, 'available_copies' => 3])->id;

        // Two independent (stale) model instances standing in for two racing
        // requests that both read the row before either committed.
        $requestA = Book::findOrFail($id);
        $requestB = Book::findOrFail($id);

        // Request A wins: decrease by 2 (guard needs available >= 2; DB has 3).
        $this->service->adjustTotalCopies($requestA, 3);

        $afterA = Book::findOrFail($id);
        $this->assertSame(3, $afterA->total_copies);
        $this->assertSame(1, $afterA->available_copies);

        // Request B applies the same decrease against its stale view. The
        // guarded UPDATE reads the *current* DB availability (now 1) and
        // rejects, rather than driving available_copies negative.
        try {
            $this->service->adjustTotalCopies($requestB, 3);
            $this->fail('Expected the losing concurrent request to be rejected.');
        } catch (InsufficientAvailableCopiesException $e) {
            $this->assertSame(2, $e->copiesOnLoan);
        }

        // Final state satisfies the invariant and reflects exactly one apply.
        $final = Book::findOrFail($id);
        $this->assertSame(3, $final->total_copies);
        $this->assertSame(1, $final->available_copies);
        $this->assertGreaterThanOrEqual(0, $final->available_copies);
        $this->assertLessThanOrEqual($final->total_copies, $final->available_copies);
    }

    public function test_two_concurrent_increases_compose_and_preserve_the_invariant(): void
    {
        $id = Book::factory()->create(['total_copies' => 5, 'available_copies' => 5])->id;

        $requestA = Book::findOrFail($id);
        $requestB = Book::findOrFail($id);

        // Both requests read total=5 and each add 3. The delta is applied to
        // the live DB row, so they compose to +6 rather than clobbering.
        $this->service->adjustTotalCopies($requestA, 8);
        $this->service->adjustTotalCopies($requestB, 8);

        $final = Book::findOrFail($id);
        $this->assertSame(11, $final->total_copies);
        $this->assertSame(11, $final->available_copies);
        $this->assertLessThanOrEqual($final->total_copies, $final->available_copies);
    }
}
