<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NearbyPickRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'viewport' => ['required', 'array'],
            'viewport.north' => ['required', 'numeric', 'between:-90,90'],
            'viewport.south' => ['required', 'numeric', 'between:-90,90', 'lt:viewport.north'],
            'viewport.east' => ['required', 'numeric', 'between:-180,180'],
            'viewport.west' => ['required', 'numeric', 'between:-180,180', 'lt:viewport.east'],
            'visiblePlaceIds' => ['required', 'array', 'min:1'],
            'visiblePlaceIds.*' => ['integer'],
            'openNow' => ['nullable', 'boolean'],
            'budgetMax' => ['nullable', 'integer', 'between:1,3'],
            'minRating' => ['nullable', 'numeric', 'between:0,5'],
        ];
    }
}
