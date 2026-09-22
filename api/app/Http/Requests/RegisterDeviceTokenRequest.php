<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterDeviceTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'installationId' => ['required', 'string', 'max:100'],
            'token' => ['required', 'string', 'max:100'],
            'environment' => ['required', 'string', 'in:sandbox,production'],
        ];
    }
}
