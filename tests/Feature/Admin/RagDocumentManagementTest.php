<?php

namespace Tests\Feature\Admin;

use App\Enums\RagDocumentStatus;
use App\Enums\UserRole;
use App\Jobs\GenerateBookEmbedding;
use App\Models\Book;
use App\Models\RagDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RagDocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    public function test_admin_sees_correct_status_summary_counts(): void
    {
        RagDocument::factory()->count(3)->completed()->create();
        RagDocument::factory()->count(2)->failed()->create();
        RagDocument::factory()->count(1)->pending()->create();
        RagDocument::factory()->count(1)->processing()->create();

        $this->actingAs($this->admin())->get('/admin/embeddings')
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/embeddings/index')
                ->where('summary.completed', 3)
                ->where('summary.failed', 2)
                ->where('summary.pending', 1)
                ->where('summary.processing', 1)
                ->has('documents.data', 7)
            );
    }

    public function test_index_can_be_filtered_by_status(): void
    {
        RagDocument::factory()->count(3)->completed()->create();
        RagDocument::factory()->count(2)->failed()->create();

        $this->actingAs($this->admin())->get('/admin/embeddings?status=failed')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filter', 'failed')
                ->has('documents.data', 2)
            );
    }

    public function test_admin_can_view_a_single_documents_full_content_and_error(): void
    {
        $document = RagDocument::factory()->failed('Provider returned 500')->create([
            'content' => 'Title: The Pragmatic Programmer\nAuthor: Hunt',
        ]);

        $this->actingAs($this->admin())->get("/admin/embeddings/{$document->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/embeddings/show')
                ->where('document.content', 'Title: The Pragmatic Programmer\nAuthor: Hunt')
                ->where('document.error_message', 'Provider returned 500')
                ->where('document.status', 'failed')
            );
    }

    public function test_show_resolves_a_document_whose_book_was_soft_deleted(): void
    {
        Queue::fake();

        $book = Book::factory()->create();
        $document = $book->ragDocuments()->first();
        $this->assertNotNull($document);
        $book->delete();

        $this->actingAs($this->admin())->get("/admin/embeddings/{$document->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('document.book.id', $book->id)
                ->where('document.book.trashed', true)
            );
    }

    public function test_retry_on_a_failed_document_resets_fields_and_dispatches_the_job(): void
    {
        Queue::fake();

        $document = RagDocument::factory()->failed()->create([
            'embedding_provider' => 'test',
            'embedding_model' => 'fake',
        ]);

        $this->actingAs($this->admin())
            ->post("/admin/embeddings/{$document->id}/retry")
            ->assertRedirect()
            ->assertSessionHas('status');

        $document->refresh();
        $this->assertSame(RagDocumentStatus::Pending, $document->status);
        $this->assertSame(0, $document->attempts);
        $this->assertNull($document->error_message);

        Queue::assertPushed(
            GenerateBookEmbedding::class,
            fn (GenerateBookEmbedding $job): bool => $job->ragDocumentId === $document->id,
        );
    }

    public function test_retry_is_rejected_on_non_failed_documents(): void
    {
        Queue::fake();

        foreach ([
            RagDocument::factory()->completed()->create(),
            RagDocument::factory()->pending()->create(),
            RagDocument::factory()->processing()->create(),
        ] as $document) {
            $before = $document->status;

            $this->actingAs($this->admin())
                ->post("/admin/embeddings/{$document->id}/retry")
                ->assertRedirect()
                ->assertSessionHas('error');

            $this->assertSame($before, $document->refresh()->status);
        }

        Queue::assertNothingPushed();
    }

    public function test_regenerate_works_regardless_of_current_status(): void
    {
        Queue::fake();

        foreach ([
            RagDocument::factory()->completed()->create(),
            RagDocument::factory()->pending()->create(),
            RagDocument::factory()->failed()->create(),
        ] as $document) {
            $this->actingAs($this->admin())
                ->post("/admin/embeddings/{$document->id}/regenerate")
                ->assertRedirect()
                ->assertSessionHas('status');

            $document->refresh();
            $this->assertSame(RagDocumentStatus::Pending, $document->status);
            $this->assertSame(0, $document->attempts);

            Queue::assertPushed(
                GenerateBookEmbedding::class,
                fn (GenerateBookEmbedding $job): bool => $job->ragDocumentId === $document->id,
            );
        }
    }

    public function test_regenerate_is_a_no_op_when_already_processing(): void
    {
        Queue::fake();

        $document = RagDocument::factory()->processing()->create();

        $this->actingAs($this->admin())
            ->post("/admin/embeddings/{$document->id}/regenerate")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(RagDocumentStatus::Processing, $document->refresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_retrying_a_processing_document_does_not_dispatch_a_duplicate_job(): void
    {
        Queue::fake();

        $document = RagDocument::factory()->processing()->create();

        $this->actingAs($this->admin())
            ->post("/admin/embeddings/{$document->id}/retry")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(RagDocumentStatus::Processing, $document->refresh()->status);
        Queue::assertNotPushed(GenerateBookEmbedding::class);
    }

    /**
     * @return array<string, array{0: UserRole}>
     */
    public static function nonAdminRoles(): array
    {
        return [
            'librarian' => [UserRole::Librarian],
            'member' => [UserRole::Member],
        ];
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admins_are_forbidden_from_every_route(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $document = RagDocument::factory()->failed()->create();

        $this->actingAs($user)->get('/admin/embeddings')->assertForbidden();
        $this->actingAs($user)->get("/admin/embeddings/{$document->id}")->assertForbidden();
        $this->actingAs($user)->post("/admin/embeddings/{$document->id}/retry")->assertForbidden();
        $this->actingAs($user)->post("/admin/embeddings/{$document->id}/regenerate")->assertForbidden();
    }

    public function test_list_response_never_includes_the_embedding_vector(): void
    {
        RagDocument::factory()->completed([0.1, 0.2, 0.3])->create();

        $this->actingAs($this->admin())->get('/admin/embeddings')
            ->assertInertia(fn (Assert $page) => $page
                ->has('documents.data', 1)
                ->missing('documents.data.0.embedding')
            );
    }

    public function test_detail_response_never_includes_the_embedding_vector(): void
    {
        $document = RagDocument::factory()->completed([0.1, 0.2, 0.3])->create();

        $this->actingAs($this->admin())->get("/admin/embeddings/{$document->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->missing('document.embedding')
            );
    }
}
