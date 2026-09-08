<?php

namespace App\Http\Requests;

use App\Models\Book;
use App\Models\Loan;
use Illuminate\Foundation\Http\FormRequest;

class StoreLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Checkout is MEMBER-only (LoanPolicy::create). Dates are never
        // client-supplied — only the book identifier is accepted below.
        return $this->user()->can('create', Loan::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The book must exist and not be soft-deleted (the default query
            // scope on the exists rule excludes trashed rows).
            'book_id' => ['required', 'integer', 'exists:books,id'],
        ];
    }

    /**
     * The validated, non-trashed book being checked out.
     */
    public function book(): Book
    {
        return Book::findOrFail($this->integer('book_id'));
    }
}
