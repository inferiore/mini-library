<?php

namespace App\Models;

use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $author
 * @property string|null $isbn
 * @property string|null $description
 * @property int|null $published_year
 * @property string|null $category
 * @property string|null $publisher
 * @property string|null $cover_path
 * @property int $total_copies
 * @property int $available_copies
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'title',
    'author',
    'isbn',
    'description',
    'published_year',
    'category',
    'publisher',
    'cover_path',
    'total_copies',
])]
class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Fields that, when changed, mark the book's RAG document stale
     * (see App\Observers\BookObserver and docs/specs/007-rag.md).
     *
     * @var list<string>
     */
    public const array EMBEDDING_RELEVANT_FIELDS = [
        'title', 'author', 'description', 'category', 'publisher',
    ];

    protected function casts(): array
    {
        return [
            'published_year' => 'integer',
            'total_copies' => 'integer',
            'available_copies' => 'integer',
        ];
    }

    /**
     * @return HasMany<Loan, $this>
     */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /**
     * The book's RAG document(s) for embedding/retrieval (spec 007). Today the
     * cardinality is 1:1, but the pivot keeps room for more per book later.
     *
     * @return BelongsToMany<RagDocument, $this>
     */
    public function ragDocuments(): BelongsToMany
    {
        return $this->belongsToMany(RagDocument::class);
    }
}
