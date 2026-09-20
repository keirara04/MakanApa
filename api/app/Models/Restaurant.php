<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'signature_dish', 'food_category', 'latitude', 'longitude', 'address', 'price_level', 'rating',
    'opening_hours', 'is_active', 'provider', 'provider_place_id', 'last_synced_at',
    'user_rating_count', 'impressions_count', 'accepted_count', 'rejected_count', 'google_types',
    'source_submission_id', 'phone', 'instagram_handle', 'tiktok_handle', 'website_url',
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
            'google_types' => 'array',
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

    public function saves(): HasMany
    {
        return $this->hasMany(RestaurantSave::class);
    }

    public function vibeVotes(): HasMany
    {
        return $this->hasMany(RestaurantVibeVote::class);
    }

    /** Pure provenance ("submitted by the community") — never ownership/edit-rights. */
    public function sourceSubmission(): BelongsTo
    {
        return $this->belongsTo(RestaurantSubmission::class, 'source_submission_id');
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(RestaurantMenuItem::class)->orderBy('sort_order');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(RestaurantPhoto::class);
    }

    public function fieldOverrides(): HasMany
    {
        return $this->hasMany(RestaurantFieldOverride::class);
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
            'user_rating_count' => $this->user_rating_count,
            'impressions_count' => $this->impressions_count,
            'accepted_count' => $this->accepted_count,
            'rejected_count' => $this->rejected_count,
            'google_types' => $this->google_types,
            'phone' => $this->phone,
            'instagram_handle' => $this->instagram_handle,
            'tiktok_handle' => $this->tiktok_handle,
            'website_url' => $this->website_url,
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
