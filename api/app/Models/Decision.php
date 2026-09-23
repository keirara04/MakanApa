<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'university_id', 'area_id', 'client_token', 'mode', 'latitude', 'longitude', 'budget_max',
    'max_distance', 'selected_restaurant_id', 'discovery_mode', 'vibe', 'installation_id', 'halal_only',
    'session_id', 'algorithm_version', 'taste_profile_version', 'selera_stage', 'intent_type', 'lens', 'tunes',
    'context_snapshot', 'weight_snapshot', 'candidate_count', 'funnel', 'exploration_level', 'fatigue_mode',
    'reason_catalog_version', 'client_choice_id', 'search_context',
])]
class Decision extends Model
{
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'halal_only' => 'boolean',
            'tunes' => 'array',
            'context_snapshot' => 'array',
            'weight_snapshot' => 'array',
            'funnel' => 'array',
            'search_context' => 'array',
            'fatigue_mode' => 'boolean',
            'exploration_level' => 'float',
            'created_at' => 'datetime',
        ];
    }

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

    public function interactions(): HasMany
    {
        return $this->hasMany(DecisionInteraction::class);
    }

    public function isBrainDecision(): bool
    {
        return $this->algorithm_version !== null && $this->algorithm_version !== 'v1';
    }
}
