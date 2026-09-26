<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GoogleLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idToken' => ['required', 'string'],
            'deviceLabel' => ['required', 'string', 'max:100'],
            // Where in the app the account was created — conversion analytics only, never trusted.
            'signupSource' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9_:.-]+$/'],
        ];
    }
}
