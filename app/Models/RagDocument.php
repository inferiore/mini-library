<?php

namespace App\Models;

use App\Enums\RagDocumentStatus;
use Database\Factories\RagDocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $content
 * @property string|null $embedding
 * @property RagDocumentStatus $status
 * @property string|null $embedding_provider
 * @property string|null $embedding_model
 * @property string|null $error_message
 * @property int $attempts
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Book> $books
 * @property-read Collection<int, Book> $booksWithTrashed
 */
class RagDocument extends Model
{
    /** @use HasFactory<RagDocumentFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * Defense-in-depth: raw embedding vectors must never appear in any API/
     * Inertia response (spec 009 FR6) — every current call site already
     * builds explicit array projections that omit it, but hiding it here too
     * means a future accidental toArray()/response($model) pass can't leak it.
     *
     * @var list<string>
     */
    protected $hidden = ['embedding'];

    protected function casts(): array
    {
        return [
            'status' => RagDocumentStatus::class,
            'attempts' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * The book(s) this document describes. Modeled as a many-to-many (spec 007
     * FR1) even though today's cardinality is 1:1, so extra documents per book
     * can be added later without a schema change.
     *
     * @return BelongsToMany<Book, $this>
     */
    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class);
    }

    /**
     * The book(s) this document describes, including soft-deleted ones. Spec
     * 009's admin view must still surface a document whose source book was
     * soft-deleted (marked as such) rather than dropping it silently — mirrors
     * Loan::book()'s baked-in withTrashed().
     *
     * @return BelongsToMany<Book, $this>
     */
    public function booksWithTrashed(): BelongsToMany
    {
        return $this->belongsToMany(Book::class)->withTrashed();
    }

    /**
     * Only documents whose embedding is generated and current — a search must
     * never surface a stale/failed/in-flight document (spec 007 FR6).
     *
     * @param  Builder<RagDocument>  $query
     */
    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', RagDocumentStatus::Completed);
    }
}
