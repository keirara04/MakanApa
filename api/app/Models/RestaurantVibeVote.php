<?php

namespace App\Models;

use App\Support\CommunityTag;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['restaurant_id', 'decision_id', 'vibe'])]
class RestaurantVibeVote extends Model
{
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'vibe' => CommunityTag::class,
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(Decision::class);
    }
}
