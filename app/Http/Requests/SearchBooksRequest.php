<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchBooksRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Same as spec 003's viewAny: any authenticated user. The route is
        // already behind the `auth` middleware; no per-book gate applies to
        // browsing/searching the catalog.
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Bounded to prevent pathological input; nullable so an empty
            // search box just returns the full listing.
            'query' => ['nullable', 'string', 'max:255'],
            // Filters are populated from existing data — a stale bookmark with
            // an unmatched value yields zero results, not a 422, so no
            // exists/in rule here by design (see spec 006 validation rules).
            'category' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
        ];
    }
}
