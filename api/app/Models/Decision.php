<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'university_id', 'area_id', 'client_token', 'mode', 'latitude', 'longitude', 'budget_max',
    'max_distance', 'selected_restaurant_id', 'discovery_mode', 'vibe', 'installation_id',
])]
class Decision extends Model
{
    const UPDATED_AT = null;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function selectedRestaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'selected_restaurant_id');
    }

    public function preferences(): HasMany
    {
        return $this->hasMany(DecisionPreference::class);
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(DecisionRecommendation::class);
    }
}
