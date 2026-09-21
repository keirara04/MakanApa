<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `password` is only required when the account actually has one — social-only accounts
 * (password === null) skip that check entirely in AuthController::destroy() since Sanctum's
 * token already proves an active, recent sign-in for those.
 */
class DeleteAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['nullable', 'string'],
        ];
    }
}
