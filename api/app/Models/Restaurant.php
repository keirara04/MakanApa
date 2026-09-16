<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'name', 'signature_dish', 'food_category', 'latitude', 'longitude', 'address', 'price_level', 'rating',
    'opening_hours', 'is_active', 'provider', 'provider_place_id', 'last_synced_at',
])]
class Restaurant extends Model
{
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'rating' => 'decimal:1',
            'opening_hours' => 'array',
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function cuisines(): BelongsToMany
    {
        return $this->belongsToMany(Cuisine::class, 'restaurant_cuisine');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'restaurant_tags');
    }

    /**
     * Normalized shape RecommendationService expects. Load cuisines/tags first
     * (eager-load to avoid N+1) — this assumes the relations are already loaded.
     */
    public function toRecommendationArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'signature_dish' => $this->signature_dish,
            'food_category' => $this->food_category,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'price_level' => $this->price_level,
            'rating' => $this->rating !== null ? (float) $this->rating : null,
            'is_active' => $this->is_active,
            'open_status' => $this->openStatus(),
            'cuisines' => $this->cuisines->pluck('slug')->all(),
            'tags' => $this->tags->pluck('name')->all(),
            'provider' => $this->provider,
            'provider_place_id' => $this->provider_place_id,
        ];
    }

    /** OPEN / CLOSED / UNKNOWN — never a nullable boolean, so "we don't know" can't collapse into true/false. */
    public function openStatus(): string
    {
        $openNow = $this->opening_hours['open_now'] ?? null;

        return match ($openNow) {
            true => 'open',
            false => 'closed',
            default => 'unknown',
        };
    }
}
