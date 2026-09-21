<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Deliberately does NOT accept an email or provider identity — the linkToken alone resolves to
 * the target account and its already-verified provider subject (see AuthController::link()).
 * The client proves ownership with a password, nothing else.
 */
class LinkAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
            'linkToken' => ['required', 'string'],
            'deviceLabel' => ['required', 'string', 'max:100'],
        ];
    }
}
