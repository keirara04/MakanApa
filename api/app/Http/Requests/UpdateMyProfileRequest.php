<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // `sometimes` on both fields gives explicit PATCH semantics: an omitted field leaves
            // the current value untouched, while `avatarKey: null` explicitly resets to no
            // preset avatar (unlike `name`, which never accepts null).
            'name' => ['sometimes', 'string', 'max:255'],
            'avatarKey' => ['sometimes', 'nullable', 'string', Rule::in(config('avatars.keys'))],
        ];
    }
}
