<?php

namespace Tests\Feature\Books;

use App\Enums\UserRole;
use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_librarian_can_create_a_book_and_available_copies_matches_total(): void
    {
        $librarian = User::factory()->create(['role' => UserRole::Librarian]);

        $response = $this->actingAs($librarian)->post('/books', [
            'title' => 'The Pragmatic Programmer',
            'author' => 'Dave Thomas',
            'total_copies' => 5,
        ]);

        $book = Book::where('title', 'The Pragmatic Programmer')->firstOrFail();
        $this->assertSame(5, $book->total_copies);
        $this->assertSame(5, $book->available_copies);
        $response->assertRedirect("/books/{$book->id}");
    }

    public function test_member_cannot_create_update_or_delete_a_book(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);
        $book = Book::factory()->create();

        $this->actingAs($member)->post('/books', [
            'title' => 'Blocked', 'author' => 'Nobody', 'total_copies' => 1,
        ])->assertForbidden();

        $this->actingAs($member)->put("/books/{$book->id}", [
            'title' => 'Blocked', 'author' => 'Nobody', 'total_copies' => 1,
        ])->assertForbidden();

        $this->actingAs($member)->delete("/books/{$book->id}")->assertForbidden();
    }

    public function test_available_copies_in_the_update_payload_is_ignored(): void
    {
        $librarian = User::factory()->create(['role' => UserRole::Librarian]);
        $book = Book::factory()->create(['total_copies' => 5, 'available_copies' => 5]);

        $this->actingAs($librarian)->put("/books/{$book->id}", [
            'title' => $book->title,
            'author' => $book->author,
            'total_copies' => 5,
            'available_copies' => 999,
        ]);

        $this->assertSame(5, $book->fresh()->available_copies);
    }

    public function test_deleting_a_book_with_no_active_loans_soft_deletes_it(): void
    {
        $librarian = User::factory()->create(['role' => UserRole::Librarian]);
        $book = Book::factory()->create();

        $this->actingAs($librarian)->delete("/books/{$book->id}")->assertRedirect('/books');

        $this->assertSoftDeleted($book);
    }

    public function test_invalid_published_year_oversized_description_and_non_image_cover_are_rejected(): void
    {
        $librarian = User::factory()->create(['role' => UserRole::Librarian]);
        Storage::fake('public');

        $response = $this->actingAs($librarian)->post('/books', [
            'title' => 'Bad Book',
            'author' => 'Someone',
            'total_copies' => 1,
            'published_year' => 1000,
            'description' => str_repeat('x', 5001),
            'cover_image' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ]);

        $response->assertSessionHasErrors(['published_year', 'description', 'cover_image']);
    }

    public function test_book_index_and_show_return_200_for_every_role(): void
    {
        $book = Book::factory()->create();

        foreach ([UserRole::Admin, UserRole::Librarian, UserRole::Member] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get('/books')->assertOk();
            $this->actingAs($user)->get("/books/{$book->id}")->assertOk();
        }
    }

    public function test_decreasing_total_copies_below_available_copies_is_rejected(): void
    {
        $librarian = User::factory()->create(['role' => UserRole::Librarian]);
        $book = Book::factory()->create(['total_copies' => 5, 'available_copies' => 5]);

        $response = $this->actingAs($librarian)->put("/books/{$book->id}", [
            'title' => $book->title,
            'author' => $book->author,
            'total_copies' => 2,
        ]);

        $response->assertSessionHasErrors('total_copies');
        $this->assertSame(5, $book->fresh()->total_copies);
    }

    public function test_a_null_isbn_does_not_collide_with_another_null_isbn(): void
    {
        $librarian = User::factory()->create(['role' => UserRole::Librarian]);
        Book::factory()->create(['isbn' => null]);

        $response = $this->actingAs($librarian)->post('/books', [
            'title' => 'Another Book', 'author' => 'Someone', 'total_copies' => 1,
        ]);

        $response->assertSessionDoesntHaveErrors('isbn');
    }
}
