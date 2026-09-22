<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UnclaimDeviceTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'installationId' => ['required', 'string', 'max:100'],
            'environment' => ['required', 'string', 'in:sandbox,production'],
        ];
    }
}
