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
            'university' => ['nullable', 'string', Rule::exists('universities', 'short_name')->where('active', true)],
        ];
    }
}
