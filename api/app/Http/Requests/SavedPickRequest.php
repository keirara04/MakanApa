<?php

namespace App\Http\Requests;

use App\Services\Brain\ContextEngine;
use App\Support\Lens;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Pick from my saved places" — the client sends its saved ids (the on-device list is the
 * source of truth; restaurant_saves is a fire-and-forget mirror that can drift). latitude/
 * longitude are the user's position, or the centroid of their saved places without location.
 */
class SavedPickRequest extends FormRequest
{
    public const MAX_SAVED_IDS = 100;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'savedPlaceIds' => ['required', 'array', 'min:1', 'max:'.self::MAX_SAVED_IDS],
            'savedPlaceIds.*' => ['integer'],
            'budgetMax' => ['nullable', 'integer', 'between:1,3'],
            'installationId' => ['nullable', 'string', 'max:100'],
            'halal' => ['nullable', 'boolean'],
            'lens' => ['nullable', Rule::enum(Lens::class)],
            'ignoreContext' => ['nullable', 'array'],
            'ignoreContext.*' => ['string', Rule::in(ContextEngine::SIGNALS)],
        ];
    }
}
