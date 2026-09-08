<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('book'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => [
                'nullable', 'string', 'max:20',
                Rule::unique('books', 'isbn')->ignore($this->route('book')),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'published_year' => ['nullable', 'integer', 'min:1450', 'max:'.date('Y')],
            'category' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'cover_image' => ['nullable', 'image', 'max:2048'],
            'total_copies' => ['required', 'integer', 'min:0'],
            // available_copies is deliberately absent — never accepted from
            // client input on update, per spec 003.
        ];
    }
}
