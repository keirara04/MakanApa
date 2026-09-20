<?php

namespace App\Http\Requests;

use App\Support\RestaurantField;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRestaurantSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'submissionType' => ['required', Rule::in(['new_place', 'edit_place', 'closure', 'reopen'])],
            'sourceType' => ['required', Rule::in(['google', 'manual'])],
            'googlePlaceId' => ['required_if:sourceType,google', 'nullable', 'string', 'max:255'],
            'restaurantId' => ['required_if:submissionType,edit_place', 'required_if:submissionType,closure', 'required_if:submissionType,reopen', 'nullable', 'integer', 'exists:restaurants,id'],
            'name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'foodCategory' => ['nullable', 'string', 'max:80'],
            'priceLevel' => ['nullable', 'integer', 'between:1,3'],
            'phone' => ['nullable', 'string', 'max:30'],
            'instagramHandle' => ['nullable', 'string', 'max:60'],
            'tiktokHandle' => ['nullable', 'string', 'max:60'],
            'websiteUrl' => ['nullable', 'string', 'max:255', 'url'],
            'menuItems' => ['nullable', 'array', 'max:100'],
            'menuItems.*.name' => ['required_with:menuItems', 'string', 'max:120'],
            'menuItems.*.description' => ['nullable', 'string', 'max:255'],
            'menuItems.*.price' => ['nullable', 'numeric', 'between:0,9999.99'],
            'menuItems.*.category' => ['nullable', 'string', 'max:60'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'locationSource' => ['required', Rule::in(['google', 'current_location', 'map_pin'])],
            'notes' => ['nullable', 'string', 'max:500'],
            'changedFields' => ['nullable', 'array'],
            'changedFields.*' => [Rule::in([...RestaurantField::OVERRIDABLE, 'menu_items'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('submissionType') === 'new_place' && $this->input('sourceType') === 'manual'
                && $this->input('locationSource') === 'google') {
                $validator->errors()->add('locationSource', 'A manual submission cannot claim a Google-sourced location.');
            }
        });
    }

    /** Trimmed text fields — these render directly in Nearby/Decide/Community once approved. */
    public function trimmed(): array
    {
        $data = $this->validated();
        foreach (['name', 'address', 'foodCategory', 'notes', 'phone', 'instagramHandle', 'tiktokHandle', 'websiteUrl'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        return $data;
    }
}
