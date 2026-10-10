<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuestLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'deviceLabel' => ['required', 'string', 'max:100'],
            'signupSource' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9_:.-]+$/'],
        ];
    }
}
