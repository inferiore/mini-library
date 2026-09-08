<?php

namespace Tests\Feature\Books;

use App\Enums\UserRole;
use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_increasing_total_copies_increases_available_copies_by_the_same_delta(): void
    {
        $librarian = User::factory()->create(['role' => UserRole::Librarian]);
        $book = Book::factory()->create(['total_copies' => 5, 'available_copies' => 3]);

        $response = $this->actingAs($librarian)
            ->put("/books/{$book->id}/inventory", ['total_copies' => 8]);

        $response->assertRedirect("/books/{$book->id}");
        $fresh = $book->fresh();
        $this->assertSame(8, $fresh->total_copies);
        // +3 delta applied to both columns: 3 + 3 = 6 available.
        $this->assertSame(6, $fresh->available_copies);
    }

    public function test_decreasing_total_copies_with_sufficient_available_copies_succeeds(): void
    {
        $librarian = User::factory()->create(['role' => UserRole::Librarian]);
        $book = Book::factory()->create(['total_copies' => 5, 'available_copies' => 4]);

        $response = $this->actingAs($librarian)
            ->put("/books/{$book->id}/inventory", ['total_copies' => 2]);

        $response->assertRedirect("/books/{$book->id}");
        $fresh = $book->fresh();
        $this->assertSame(2, $fresh->total_copies);
        // -3 delta: 4 - 3 = 1 available.
        $this->assertSame(1, $fresh->available_copies);
    }

    public function test_decreasing_total_copies_to_exactly_the_on_loan_count_is_allowed(): void
    {
        $librarian = User::factory()->create(['role' => UserRole::Librarian]);
        // 3 on loan (5 total, 2 available).
        $book = Book::factory()->create(['total_copies' => 5, 'available_copies' => 2]);

        $response = $this->actingAs($librarian)
            ->put("/books/{$book->id}/inventory", ['total_copies' => 3]);

        $response->assertSessionHasNoErrors();
        $fresh = $book->fresh();
        $this->assertSame(3, $fresh->total_copies);
        $this->assertSame(0, $fresh->available_copies);
    }

    public function test_decreasing_total_copies_below_the_on_loan_count_is_rejected_with_the_count(): void
    {
        $librarian = User::factory()->create(['role' => UserRole::Librarian]);
        // 3 on loan (5 total, 2 available).
        $book = Book::factory()->create(['total_copies' => 5, 'available_copies' => 2]);

        $response = $this->actingAs($librarian)
            ->put("/books/{$book->id}/inventory", ['total_copies' => 2]);

        $response->assertSessionHasErrors('total_copies');
        $message = session('errors')->first('total_copies');
        $this->assertStringContainsString('3', $message);

        // State untouched.
        $fresh = $book->fresh();
        $this->assertSame(5, $fresh->total_copies);
        $this->assertSame(2, $fresh->available_copies);
    }

    public function test_member_cannot_adjust_inventory(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);
        $book = Book::factory()->create(['total_copies' => 5, 'available_copies' => 5]);

        $this->actingAs($member)
            ->put("/books/{$book->id}/inventory", ['total_copies' => 10])
            ->assertForbidden();

        $this->assertSame(5, $book->fresh()->total_copies);
    }

    public function test_admin_can_adjust_inventory(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $book = Book::factory()->create(['total_copies' => 5, 'available_copies' => 5]);

        $this->actingAs($admin)
            ->put("/books/{$book->id}/inventory", ['total_copies' => 7])
            ->assertRedirect("/books/{$book->id}");

        $this->assertSame(7, $book->fresh()->total_copies);
    }

    public function test_new_total_copies_must_be_a_non_negative_integer(): void
    {
        $librarian = User::factory()->create(['role' => UserRole::Librarian]);
        $book = Book::factory()->create(['total_copies' => 5, 'available_copies' => 5]);

        $this->actingAs($librarian)
            ->put("/books/{$book->id}/inventory", ['total_copies' => -1])
            ->assertSessionHasErrors('total_copies');

        $this->actingAs($librarian)
            ->put("/books/{$book->id}/inventory", [])
            ->assertSessionHasErrors('total_copies');
    }
}
