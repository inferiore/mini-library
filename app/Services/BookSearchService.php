<?php

namespace App\Services;

use App\Models\Book;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class BookSearchService
{
    /**
     * Lexical catalog search over title/author/isbn/category/publisher and
     * (matching within) description.
     *
     * Built entirely on the Eloquent builder so results are real Book models
     * (casts, soft-delete scoping, accessors all apply). The driver is only
     * consulted to pick the raw SQL fragment for the free-text clause: Postgres
     * uses the generated `search_vector` tsvector with relevance ranking;
     * SQLite falls back to LIKE across the same field set. Category/publisher
     * are plain equality filters that compose on top of either branch.
     *
     * An empty or whitespace-only query applies no search clause at all — the
     * result is identical to the plain paginated listing from spec 003.
     *
     * @return LengthAwarePaginator<int, Book>
     */
    public function search(?string $query, ?string $category = null, ?string $publisher = null): LengthAwarePaginator
    {
        $term = trim((string) $query);

        $builder = Book::query();

        if ($term !== '') {
            $this->applyTextSearch($builder, $term);
        } else {
            $builder->orderBy('title');
        }

        $builder
            ->when($category, fn (Builder $q) => $q->where('category', $category))
            ->when($publisher, fn (Builder $q) => $q->where('publisher', $publisher));

        return $builder->paginate(20)->withQueryString();
    }

    /**
     * @param  Builder<Book>  $builder
     */
    private function applyTextSearch(Builder $builder, string $term): void
    {
        // ISBN is matched via LIKE on both drivers — ISBNs don't tokenize
        // meaningfully as prose, so full-text search alone wouldn't find a
        // partial ISBN. websearch_to_tsquery tolerates free-text/special chars
        // (&, :, ') gracefully, and every fragment below is parameter-bound, so
        // neither path can be broken by special characters in the term.
        $like = '%'.$this->escapeLike($term).'%';

        if (DB::connection()->getDriverName() === 'pgsql') {
            $builder
                ->where(function (Builder $q) use ($term, $like): void {
                    $q->whereRaw('search_vector @@ websearch_to_tsquery(?)', [$term])
                        ->orWhereRaw("isbn LIKE ? ESCAPE '\\'", [$like]);
                })
                ->orderByRaw('ts_rank(search_vector, websearch_to_tsquery(?)) desc', [$term])
                ->orderBy('title');

            return;
        }

        $builder
            ->where(function (Builder $q) use ($like): void {
                foreach (['title', 'author', 'isbn', 'category', 'publisher', 'description'] as $column) {
                    $q->orWhereRaw("{$column} LIKE ? ESCAPE '\\'", [$like]);
                }
            })
            ->orderBy('title');
    }

    /**
     * Escape LIKE wildcards (%, _) and the escape char itself so a user typing
     * them searches for the literal character rather than a wildcard. Paired
     * with an explicit `ESCAPE '\'` clause, which both SQLite and Postgres
     * honor (SQLite's LIKE has no default escape character otherwise).
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
