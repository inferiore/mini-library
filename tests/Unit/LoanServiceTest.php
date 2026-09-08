<?php

namespace Tests\Unit;

use App\Exceptions\AlreadyBorrowedException;
use App\Exceptions\BookUnavailableException;
use App\Exceptions\LoanAlreadyReturnedException;
use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanServiceTest extends TestCase
{
    use RefreshDatabase;

    private LoanService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LoanService;
    }

    public function test_checkout_creates_a_loan_and_decrements_availability(): void
    {
        config(['library.loan_period_days' => 14]);
        $book = Book::factory()->create(['total_copies' => 3, 'available_copies' => 3]);
        $user = User::factory()->create();

        $loan = $this->service->checkout($book, $user);

        $this->assertNull($loan->returned_at);
        $this->assertSame($book->id, $loan->book_id);
        $this->assertSame($user->id, $loan->user_id);
        // due_at is checked_out_at + loan_period_days.
        $this->assertTrue(
            $loan->due_at->equalTo($loan->checked_out_at->copy()->addDays(14))
        );
        $this->assertSame(2, $book->fresh()->available_copies);
    }

    public function test_checkout_is_rejected_when_no_copies_are_available(): void
    {
        $book = Book::factory()->create(['total_copies' => 1, 'available_copies' => 0]);
        $user = User::factory()->create();

        $this->expectException(BookUnavailableException::class);

        try {
            $this->service->checkout($book, $user);
        } finally {
            $this->assertSame(0, $book->fresh()->available_copies);
            $this->assertSame(0, Loan::count());
        }
    }

    public function test_checkout_is_rejected_when_member_already_holds_the_book(): void
    {
        $book = Book::factory()->create(['total_copies' => 3, 'available_copies' => 3]);
        $user = User::factory()->create();

        $this->service->checkout($book, $user);

        try {
            $this->service->checkout($book, $user);
            $this->fail('Expected AlreadyBorrowedException.');
        } catch (AlreadyBorrowedException) {
            // The duplicate attempt must not have touched inventory again.
            $this->assertSame(2, $book->fresh()->available_copies);
            $this->assertSame(1, Loan::count());
        }
    }

    public function test_return_sets_returned_at_and_increments_availability(): void
    {
        $book = Book::factory()->create(['total_copies' => 3, 'available_copies' => 2]);
        $loan = Loan::factory()->create([
            'book_id' => $book->id,
            'returned_at' => null,
        ]);

        $returned = $this->service->return($loan);

        $this->assertNotNull($returned->returned_at);
        $this->assertSame(3, $book->fresh()->available_copies);
    }

    public function test_returning_an_already_returned_loan_is_rejected_without_double_incrementing(): void
    {
        $book = Book::factory()->create(['total_copies' => 3, 'available_copies' => 2]);
        $loan = Loan::factory()->create(['book_id' => $book->id, 'returned_at' => null]);

        $this->service->return($loan);
        $this->assertSame(3, $book->fresh()->available_copies);

        try {
            $this->service->return($loan->fresh());
            $this->fail('Expected LoanAlreadyReturnedException.');
        } catch (LoanAlreadyReturnedException) {
            // No second increment — availability stays capped, never exceeds total.
            $this->assertSame(3, $book->fresh()->available_copies);
        }
    }

    public function test_overdue_scope_and_accessor_only_flag_active_past_due_loans(): void
    {
        $book = Book::factory()->create();

        $overdue = Loan::factory()->overdue()->create(['book_id' => $book->id]);
        $current = Loan::factory()->create([
            'book_id' => $book->id,
            'due_at' => now()->addDays(5),
            'returned_at' => null,
        ]);
        $returnedPastDue = Loan::factory()->create([
            'book_id' => $book->id,
            'due_at' => now()->subDays(5),
            'returned_at' => now(),
        ]);

        $this->assertTrue($overdue->isOverdue());
        $this->assertFalse($current->isOverdue());
        $this->assertFalse($returnedPastDue->isOverdue());

        $overdueIds = Loan::overdue()->pluck('id')->all();
        $this->assertContains($overdue->id, $overdueIds);
        $this->assertNotContains($current->id, $overdueIds);
        $this->assertNotContains($returnedPastDue->id, $overdueIds);
    }

    public function test_concurrent_checkout_of_the_last_copy_lets_exactly_one_succeed(): void
    {
        // One copy available; five members each try to check it out.
        $book = Book::factory()->create(['total_copies' => 1, 'available_copies' => 1]);
        $users = User::factory()->count(5)->create();

        $successes = 0;
        $failures = 0;

        foreach ($users as $user) {
            try {
                $this->service->checkout($book, $user);
                $successes++;
            } catch (BookUnavailableException) {
                $failures++;
            }
        }

        // Exactly one guarded UPDATE affected the row; the rest saw 0 affected.
        $this->assertSame(1, $successes);
        $this->assertSame(4, $failures);
        $this->assertSame(1, Loan::count());
        // Availability floored at zero — never negative.
        $this->assertSame(0, $book->fresh()->available_copies);
    }
}
