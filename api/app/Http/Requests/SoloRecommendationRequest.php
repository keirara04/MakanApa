<?php

namespace App\Http\Requests;

use App\Support\DiscoveryMode;
use App\Support\Vibe;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'craving' => ['nullable', 'string', 'max:100'],
            'mode' => ['nullable', Rule::enum(DiscoveryMode::class)],
            'vibe' => ['nullable', Rule::enum(Vibe::class)],
            'installationId' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * The iOS single-select UI guarantees a request never has both a curated tag and a custom
     * craving, but a request isn't required to come from that client — enforce the invariant
     * server-side too, since RecommendationService's scoring assumes moodTags/craving are
     * mutually exclusive (it reuses one weight slot for whichever is present).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $hasMoods = ! empty($this->input('moods', []));
            $hasCraving = ! empty(trim((string) $this->input('craving', '')));

            if ($hasMoods && $hasCraving) {
                $validator->errors()->add('craving', 'Send either moods or craving, not both.');
            }
        });
    }
}
