<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RagDocumentStatus;
use App\Http\Controllers\Controller;
use App\Models\RagDocument;
use App\Services\RagDocumentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin-only visibility and control over the RAG embedding pipeline (spec 009).
 * Every action is admin-gated twice: the `role:admin` route group and the
 * RagDocumentPolicy checks below. Raw embedding vectors are never mapped into
 * any response payload here — only content, status, and metadata are exposed.
 */
class RagDocumentController extends Controller
{
    public function __construct(private readonly RagDocumentService $documents) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', RagDocument::class);

        $filter = request('status');
        $validFilter = $filter !== null
            ? RagDocumentStatus::tryFrom((string) $filter)
            : null;

        $documents = RagDocument::query()
            ->with('booksWithTrashed')
            ->when($validFilter !== null, fn (Builder $q) => $q->where('status', $validFilter))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (RagDocument $document): array => $this->summarize($document));

        return Inertia::render('admin/embeddings/index', [
            'documents' => $documents,
            'filter' => $validFilter?->value,
            // Actual, freshly-computed table state — not a cached count — so the
            // health summary always matches what the list would show.
            'summary' => $this->statusSummary(),
        ]);
    }

    public function show(RagDocument $ragDocument): Response
    {
        Gate::authorize('view', $ragDocument);

        // A soft-deleted book must still resolve so its document doesn't vanish
        // or error out of the admin's view (spec 009 edge case).
        $ragDocument->load('booksWithTrashed');

        return Inertia::render('admin/embeddings/show', [
            'document' => $this->detail($ragDocument),
        ]);
    }

    /**
     * Retry a failed embedding. Rejected with a clear error when the document
     * isn't currently `failed` (this covers the concurrent-`processing` case:
     * a doc the pipeline has already picked up is no longer `failed`, so no
     * duplicate job is dispatched).
     */
    public function retry(RagDocument $ragDocument): RedirectResponse
    {
        Gate::authorize('retry', $ragDocument);

        $ragDocument->refresh();

        if ($ragDocument->status !== RagDocumentStatus::Failed) {
            return back()->with('error', 'Only failed documents can be retried.');
        }

        if (! $this->documents->requeue($ragDocument)) {
            return back()->with('error', 'This document is already being processed.');
        }

        return back()->with('status', 'Embedding retry queued.');
    }

    /**
     * Regenerate an embedding from any status except `processing` — the guarded
     * requeue is a no-op when a job is already in flight, so it can't be
     * duplicated (spec 009 edge case).
     */
    public function regenerate(RagDocument $ragDocument): RedirectResponse
    {
        Gate::authorize('regenerate', $ragDocument);

        $ragDocument->refresh();

        if ($ragDocument->status === RagDocumentStatus::Processing
            || ! $this->documents->requeue($ragDocument)) {
            return back()->with('error', 'This document is already being processed.');
        }

        return back()->with('status', 'Embedding regeneration queued.');
    }

    /**
     * List-row projection — explicitly excludes the `embedding` column.
     *
     * @return array<string, mixed>
     */
    private function summarize(RagDocument $document): array
    {
        return [
            'id' => $document->id,
            'status' => $document->status->value,
            'embedding_provider' => $document->embedding_provider,
            'embedding_model' => $document->embedding_model,
            'attempts' => $document->attempts,
            'processed_at' => $document->processed_at?->toISOString(),
            'content_preview' => Str::limit($document->content, 120),
            'book' => $this->bookSummary($document),
        ];
    }

    /**
     * Detail projection — full content + error, still no `embedding`.
     *
     * @return array<string, mixed>
     */
    private function detail(RagDocument $document): array
    {
        return [
            'id' => $document->id,
            'status' => $document->status->value,
            'content' => $document->content,
            'error_message' => $document->error_message,
            'embedding_provider' => $document->embedding_provider,
            'embedding_model' => $document->embedding_model,
            'attempts' => $document->attempts,
            'processed_at' => $document->processed_at?->toISOString(),
            'created_at' => $document->created_at?->toISOString(),
            'updated_at' => $document->updated_at?->toISOString(),
            'book' => $this->bookSummary($document),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function bookSummary(RagDocument $document): ?array
    {
        $book = $document->booksWithTrashed->first();

        if ($book === null) {
            return null;
        }

        return [
            'id' => $book->id,
            'title' => $book->title,
            'trashed' => $book->trashed(),
        ];
    }

    /**
     * Count per status, with every status present (0 when none), computed from
     * the live table.
     *
     * @return array<string, int>
     */
    private function statusSummary(): array
    {
        $counts = RagDocument::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $summary = [];

        foreach (RagDocumentStatus::cases() as $status) {
            $summary[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $summary;
    }
}
