<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AcceptTermsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // The versions the app actually showed — checked against config/legal.php in the
        // controller, so an agreement to a stale document is refused rather than recorded.
        return [
            'termsVersion' => ['required', 'string', 'max:20'],
            'guidelinesVersion' => ['required', 'string', 'max:20'],
            'privacyVersion' => ['required', 'string', 'max:20'],
            'appVersion' => ['nullable', 'string', 'max:40'],
        ];
    }
}
