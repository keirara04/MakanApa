<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Verified owner link — tags evidence as restaurant_owner, never bypasses moderation. */
#[Fillable(['restaurant_id', 'user_id', 'status', 'verified_by', 'verified_at', 'claim_submission_id'])]
class RestaurantOwner extends Model
{
    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function isVerifiedOwner(int $userId, int $restaurantId): bool
    {
        return self::where('user_id', $userId)
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'verified')
            ->exists();
    }
}
