<?php

namespace Tests\Feature\Loans;

use App\Enums\UserRole;
use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_return_their_own_active_loan(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);
        $book = Book::factory()->create(['total_copies' => 3, 'available_copies' => 2]);
        $loan = Loan::factory()->create([
            'book_id' => $book->id,
            'user_id' => $member->id,
            'returned_at' => null,
        ]);

        $response = $this->actingAs($member)->put("/loans/{$loan->id}");

        $response->assertSessionHas('status', 'Book returned.');
        $this->assertNotNull($loan->fresh()->returned_at);
        $this->assertSame(3, $book->fresh()->available_copies);
    }

    public function test_returning_an_already_returned_loan_is_rejected_and_does_not_double_increment(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);
        $book = Book::factory()->create(['total_copies' => 3, 'available_copies' => 2]);
        $loan = Loan::factory()->create([
            'book_id' => $book->id,
            'user_id' => $member->id,
            'returned_at' => null,
        ]);

        $this->actingAs($member)->put("/loans/{$loan->id}");
        $this->assertSame(3, $book->fresh()->available_copies);

        $this->actingAs($member)->put("/loans/{$loan->id}")
            ->assertSessionHas('error');

        // No second increment.
        $this->assertSame(3, $book->fresh()->available_copies);
    }

    public function test_staff_can_return_a_loan_on_behalf_of_a_member(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);

        foreach ([UserRole::Admin, UserRole::Librarian] as $role) {
            $book = Book::factory()->create(['total_copies' => 3, 'available_copies' => 2]);
            $loan = Loan::factory()->create([
                'book_id' => $book->id,
                'user_id' => $member->id,
                'returned_at' => null,
            ]);

            $staff = User::factory()->create(['role' => $role]);
            $this->actingAs($staff)->put("/loans/{$loan->id}")
                ->assertSessionHas('status', 'Book returned.');

            $this->assertNotNull($loan->fresh()->returned_at);
            $this->assertSame(3, $book->fresh()->available_copies);
        }
    }

    public function test_a_member_cannot_return_another_members_loan(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Member]);
        $other = User::factory()->create(['role' => UserRole::Member]);
        $book = Book::factory()->create(['total_copies' => 3, 'available_copies' => 2]);
        $loan = Loan::factory()->create([
            'book_id' => $book->id,
            'user_id' => $owner->id,
            'returned_at' => null,
        ]);

        $this->actingAs($other)->put("/loans/{$loan->id}")->assertForbidden();

        $this->assertNull($loan->fresh()->returned_at);
        $this->assertSame(2, $book->fresh()->available_copies);
    }
}
