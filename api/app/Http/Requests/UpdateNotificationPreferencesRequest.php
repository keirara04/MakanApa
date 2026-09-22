<?php

namespace App\Http\Requests;

use App\Support\NotificationCategory;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return array_fill_keys(NotificationCategory::ALL, ['sometimes', 'boolean']);
    }
}
