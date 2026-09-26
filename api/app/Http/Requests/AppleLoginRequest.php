<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AppleLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identityToken' => ['required', 'string'],
            'authorizationCode' => ['required', 'string'],
            'nonce' => ['required', 'string'],
            // Only present on the very first authorization for a given Apple ID — Apple never
            // resends it on subsequent sign-ins.
            'fullName' => ['nullable', 'string', 'max:100'],
            'deviceLabel' => ['required', 'string', 'max:100'],
            // Where in the app the account was created — conversion analytics only, never trusted.
            'signupSource' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9_:.-]+$/'],
        ];
    }
}
