<?php

namespace Tests\Feature\Loans;

use App\Enums\UserRole;
use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LoanIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_loans_only_returns_the_current_members_loans(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);
        $other = User::factory()->create(['role' => UserRole::Member]);
        $book = Book::factory()->create();

        Loan::factory()->count(2)->create(['user_id' => $member->id, 'book_id' => $book->id, 'returned_at' => now()]);
        Loan::factory()->create(['user_id' => $other->id, 'book_id' => $book->id]);

        $this->actingAs($member)->get('/my-loans')
            ->assertInertia(fn (Assert $page) => $page
                ->component('loans/my-loans')
                ->has('loans.data', 2)
                ->where('loans.data.0.user_id', $member->id)
                ->where('loans.data.1.user_id', $member->id)
            );
    }

    public function test_all_loans_index_returns_every_users_loans_for_staff(): void
    {
        $memberA = User::factory()->create(['role' => UserRole::Member]);
        $memberB = User::factory()->create(['role' => UserRole::Member]);
        $book = Book::factory()->create();

        Loan::factory()->create(['user_id' => $memberA->id, 'book_id' => $book->id]);
        Loan::factory()->create(['user_id' => $memberB->id, 'book_id' => $book->id, 'returned_at' => now()]);

        $librarian = User::factory()->create(['role' => UserRole::Librarian]);

        $this->actingAs($librarian)->get('/loans')
            ->assertInertia(fn (Assert $page) => $page
                ->component('loans/index')
                ->has('loans.data', 2)
            );
    }

    public function test_members_cannot_view_the_all_loans_index(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);

        $this->actingAs($member)->get('/loans')->assertForbidden();
    }

    public function test_all_loans_index_can_be_filtered_by_status(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);
        // Distinct books: a member can't hold two active loans of one book
        // (partial unique index), so the active + overdue loans need separate
        // books to coexist.
        $bookA = Book::factory()->create();
        $bookB = Book::factory()->create();
        $bookC = Book::factory()->create();

        Loan::factory()->create(['user_id' => $member->id, 'book_id' => $bookA->id, 'due_at' => now()->addDays(5)]);
        $overdue = Loan::factory()->overdue()->create(['user_id' => $member->id, 'book_id' => $bookB->id]);
        Loan::factory()->create(['user_id' => $member->id, 'book_id' => $bookC->id, 'returned_at' => now()]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get('/loans?filter=active')
            ->assertInertia(fn (Assert $page) => $page->has('loans.data', 2));

        $this->actingAs($admin)->get('/loans?filter=overdue')
            ->assertInertia(fn (Assert $page) => $page
                ->has('loans.data', 1)
                ->where('loans.data.0.id', $overdue->id)
            );

        $this->actingAs($admin)->get('/loans?filter=returned')
            ->assertInertia(fn (Assert $page) => $page->has('loans.data', 1));
    }
}
