<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMyAffiliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Retired universities stay in the admin-facing exists check (CreateUserRequest) so
            // historical rows still resolve, but self-service picks only from what's live today.
            // University and area are mutually exclusive — picking one clears the other; neither
            // set at all means Public.
            'university' => ['nullable', 'string', 'prohibits:area', Rule::exists('universities', 'short_name')->where('active', true)],
            'area' => ['nullable', 'string', 'prohibits:university', Rule::exists('areas', 'short_name')->where('active', true)],
        ];
    }
}
