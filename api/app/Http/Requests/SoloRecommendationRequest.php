<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SoloRecommendationRequest extends FormRequest
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
            'budgetMax' => ['nullable', 'integer', 'between:1,3'],
            'maxDistanceKm' => ['required', 'numeric', 'between:0.1,50'],
            'moods' => ['array'],
            'moods.*' => ['string'],
        ];
    }
}
