<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)],
            'deviceLabel' => ['required', 'string', 'max:100'],
            // Where in the app the account was created — conversion analytics only, never trusted.
            'signupSource' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9_:.-]+$/'],
        ];
    }
}
