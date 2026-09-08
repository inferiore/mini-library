<?php

namespace App\Http\Requests;

use App\Models\Book;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Book::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            // Rule::unique() queries the raw table directly, bypassing the
            // model's SoftDeletingScope — soft-deleted rows are included in
            // this uniqueness check by default, no extra method needed.
            'isbn' => ['nullable', 'string', 'max:20', Rule::unique('books', 'isbn')],
            'description' => ['nullable', 'string', 'max:5000'],
            'published_year' => ['nullable', 'integer', 'min:1450', 'max:'.date('Y')],
            'category' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'cover_image' => ['nullable', 'image', 'max:2048'],
            'total_copies' => ['required', 'integer', 'min:0'],
        ];
    }
}
