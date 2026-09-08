<?php

namespace App\Models;

use Database\Factories\LoanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $book_id
 * @property int $user_id
 * @property Carbon $checked_out_at
 * @property Carbon $due_at
 * @property Carbon|null $returned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Book $book
 * @property-read User $user
 * @property-read bool $is_overdue
 */
#[Fillable(['book_id', 'user_id', 'checked_out_at', 'due_at', 'returned_at'])]
class Loan extends Model
{
    /** @use HasFactory<LoanFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'checked_out_at' => 'datetime',
            'due_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        // withTrashed: loan history must still render the book even after it's
        // been soft-deleted from the catalog (spec 005 edge case).
        return $this->belongsTo(Book::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Active loans are those not yet returned.
     *
     * @param  Builder<Loan>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('returned_at');
    }

    /**
     * Overdue loans are active loans past their due date. "Overdue" is never
     * stored — always derived — so it can't desync (spec 005 FR5).
     *
     * @param  Builder<Loan>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->active()->where('due_at', '<', now());
    }

    public function isOverdue(): bool
    {
        return $this->returned_at === null && $this->due_at->isPast();
    }
}
