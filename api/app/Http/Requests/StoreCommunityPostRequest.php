<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;

/** Shape only — the content filter, community scoping and reply rules live in CommunityPostService. */
class StoreCommunityPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $max = (int) Config::get('moderation.community_posts.max_length', 280);

        return [
            'body' => ['required', 'string', "max:{$max}"],
            'restaurantId' => ['nullable', 'integer'],
            'parentId' => ['nullable', 'integer'],
        ];
    }
}
