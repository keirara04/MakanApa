<?php

namespace App\Models;

use App\Support\CommunityReaction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['community_post_id', 'user_id', 'type'])]
class CommunityPostReaction extends Model
{
    protected function casts(): array
    {
        return [
            'type' => CommunityReaction::class,
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(CommunityPost::class, 'community_post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
