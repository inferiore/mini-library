<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecommendationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Any authenticated user (member/librarian/admin) may request
        // recommendations (spec 008 authorization). The route already sits
        // behind the `auth` middleware; nothing here is role-sensitive.
        return $this->user() !== null;
    }

    /**
     * Trim the query first so a whitespace-only value collapses to empty and
     * is caught by the `min` rule — we never spend an embedding/LLM call on
     * effectively-empty input (spec 008 edge cases).
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'query' => $this->string('query')->trim()->value(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Long enough to be meaningful, short enough to bound embedding/LLM
            // cost per request (spec 008 validation rules).
            'query' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
