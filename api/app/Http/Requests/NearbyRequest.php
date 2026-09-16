<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NearbyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'north' => ['required', 'numeric', 'between:-90,90'],
            'south' => ['required', 'numeric', 'between:-90,90', 'lt:north'],
            'east' => ['required', 'numeric', 'between:-180,180'],
            'west' => ['required', 'numeric', 'between:-180,180', 'lt:east'],
            'openNow' => ['nullable', 'boolean'],
            'budgetMax' => ['nullable', 'integer', 'between:1,3'],
            'minRating' => ['nullable', 'numeric', 'between:0,5'],
        ];
    }
}
