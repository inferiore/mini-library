<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdjustInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('adjustInventory', $this->route('book'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Only total_copies is ever accepted here — available_copies is
            // never client-settable (spec 004 FR1); it is derived by the
            // service from the delta.
            'total_copies' => ['required', 'integer', 'min:0'],
        ];
    }
}
