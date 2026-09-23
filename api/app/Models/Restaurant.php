<?php

namespace App\Models;

use App\Support\Halal\HalalReviewState;
use App\Support\Halal\HalalStatus;
use App\Support\OpeningHours;
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
    'merged_into_restaurant_id', 'halal_status', 'halal_review_state', 'halal_active_verification_id',
    'halal_active_certificate_id', 'halal_verified_at', 'halal_expires_at', 'halal_open_report_count',
    'halal_ai_hint',
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
            'halal_status' => HalalStatus::class,
            'halal_review_state' => HalalReviewState::class,
            'halal_verified_at' => 'datetime',
            'halal_expires_at' => 'date',
            'halal_ai_hint' => 'array',
        ];
    }

    public function halalVerifications(): HasMany
    {
        return $this->hasMany(RestaurantHalalVerification::class);
    }

    public function halalCertificates(): HasMany
    {
        return $this->hasMany(RestaurantHalalCertificate::class);
    }

    public function activeHalalVerification(): BelongsTo
    {
        return $this->belongsTo(RestaurantHalalVerification::class, 'halal_active_verification_id');
    }

    public function activeHalalCertificate(): BelongsTo
    {
        return $this->belongsTo(RestaurantHalalCertificate::class, 'halal_active_certificate_id');
    }

    public function owners(): HasMany
    {
        return $this->hasMany(RestaurantOwner::class);
    }

    /** True once a certified snapshot's cert expiry has passed — read-time, independent of halal:lifecycle. */
    public function isHalalCertificateExpired(): bool
    {
        return $this->halal_status === HalalStatus::Certified
            && $this->halal_expires_at !== null
            && $this->halal_expires_at->lt(today());
    }

    /** A lapsed certificate (expired at read time, or already processed by halal:lifecycle). */
    public function needsHalalReverification(): bool
    {
        return $this->isHalalCertificateExpired()
            || $this->halal_review_state === HalalReviewState::ReverifyRequired;
    }

    /**
     * The status every filter/ranking/presentation path must use — never raw `halal_status`.
     * An expired certificate degrades to Unknown so a late lifecycle job can't keep it "Halal".
     */
    public function effectiveHalalStatus(): HalalStatus
    {
        if ($this->isHalalCertificateExpired()) {
            return HalalStatus::Unknown;
        }

        return $this->halal_status ?? HalalStatus::Unknown;
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

    /** The restaurant this one was merged away into by RestaurantMergeService, if any. */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'merged_into_restaurant_id');
    }

    /** Duplicates that were merged away into this restaurant. */
    public function mergedFrom(): HasMany
    {
        return $this->hasMany(Restaurant::class, 'merged_into_restaurant_id');
    }

    /**
     * The row admins/sync should actually act on. A merged-away restaurant keeps its own
     * provider_place_id (RestaurantMergeService never clears it — see PlacesService's lookup
     * sites), so anything that finds a restaurant by provider_place_id or by ID must resolve
     * through this before writing, or it can resurrect a duplicate that was deliberately merged
     * away. Single hop only — RestaurantMergeService refuses to merge into an already-merged-away
     * row, so merged_into_restaurant_id never chains.
     */
    public function canonicalRestaurant(): self
    {
        return $this->merged_into_restaurant_id === null
            ? $this
            : ($this->mergedInto ?? Restaurant::findOrFail($this->merged_into_restaurant_id));
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
            // Effective (expiry-applied) status — every filter/ranking path reads this key, never
            // the raw column. Authority/reverify feed HalalPresenter::summaryFromArray() for markers.
            'halal_status' => $this->effectiveHalalStatus()->value,
            'halal_authority' => $this->halal_active_certificate_id !== null
                ? $this->activeHalalCertificate?->authority?->value
                : null,
            'halal_reverify' => $this->needsHalalReverification(),
        ];
    }

    /**
     * Search-result shape for PlacesService::searchPlaces()/resolveGooglePlace() — builds on
     * toRecommendationArray() rather than duplicating its fields, adding only what a search result
     * card needs and pure ranking/join data (toRecommendationArray() doesn't know about) doesn't.
     *
     * @param  'canonical'|'community'  $provenance  never 'google_fallback' here — this model is
     *                                               only ever a row already in `restaurants`.
     */
    public function toSearchResultArray(string $provenance, ?float $distanceKm = null): array
    {
        return [
            ...$this->toRecommendationArray(),
            'address' => $this->address,
            'closes_at' => OpeningHours::closesAt($this->opening_hours, now()),
            'provenance' => $provenance,
            'is_community_find' => $provenance === 'community',
            'distance_km' => $distanceKm,
            'google_place_id' => null,
        ];
    }

    /**
     * OPEN / CLOSED / UNKNOWN — never a nullable boolean, so "we don't know" can't collapse into
     * true/false. Worked out at read time from the stored weekly hours (see OpeningHours).
     */
    public function openStatus(): string
    {
        return OpeningHours::status($this->opening_hours, now());
    }
}
