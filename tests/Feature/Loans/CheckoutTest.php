<?php

namespace Tests\Feature\Loans;

use App\Enums\UserRole;
use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_check_out_an_available_book(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);
        $book = Book::factory()->create(['total_copies' => 3, 'available_copies' => 3]);

        $response = $this->actingAs($member)->post('/loans', ['book_id' => $book->id]);

        $response->assertRedirect("/books/{$book->id}");
        $response->assertSessionHas('status', 'Book checked out.');

        $loan = Loan::firstOrFail();
        $this->assertSame($book->id, $loan->book_id);
        $this->assertSame($member->id, $loan->user_id);
        $this->assertNull($loan->returned_at);
        $this->assertTrue(
            $loan->due_at->equalTo(
                $loan->checked_out_at->copy()->addDays(config('library.loan_period_days'))
            )
        );
        $this->assertSame(2, $book->fresh()->available_copies);
    }

    public function test_checkout_is_rejected_when_no_copies_are_available(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);
        $book = Book::factory()->create(['total_copies' => 1, 'available_copies' => 0]);

        $response = $this->actingAs($member)->post('/loans', ['book_id' => $book->id]);

        $response->assertSessionHas('error');
        $this->assertSame(0, Loan::count());
        $this->assertSame(0, $book->fresh()->available_copies);
    }

    public function test_checkout_is_rejected_for_a_book_the_member_already_holds(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);
        $book = Book::factory()->create(['total_copies' => 3, 'available_copies' => 3]);

        $this->actingAs($member)->post('/loans', ['book_id' => $book->id]);
        $this->actingAs($member)->post('/loans', ['book_id' => $book->id])
            ->assertSessionHas('error');

        $this->assertSame(1, Loan::count());
        // Only one copy was ever decremented.
        $this->assertSame(2, $book->fresh()->available_copies);
    }

    public function test_staff_cannot_check_out_books(): void
    {
        $book = Book::factory()->create(['total_copies' => 3, 'available_copies' => 3]);

        foreach ([UserRole::Admin, UserRole::Librarian] as $role) {
            $staff = User::factory()->create(['role' => $role]);
            $this->actingAs($staff)->post('/loans', ['book_id' => $book->id])
                ->assertForbidden();
        }

        $this->assertSame(0, Loan::count());
        $this->assertSame(3, $book->fresh()->available_copies);
    }
}
