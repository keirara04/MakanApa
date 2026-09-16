<?php

namespace App\Services\Places;

use App\Models\Restaurant;
use Illuminate\Support\Collection;

/**
 * Reads the Phase 1 fixture-seeded restaurants directly from Postgres.
 * Already deals in normalized data, so it returns the same shape
 * RecommendationService expects rather than round-tripping through a
 * fake ProviderPlace. Ignores lat/lng/radius pre-filtering — the small
 * fixture set doesn't need a bounding-box query, and RecommendationService's
 * own distance filter enforces the real cutoff.
 */
class FixturePlacesProvider implements PlacesProvider
{
    public function nearbyRestaurants(float $latitude, float $longitude, float $radiusKm): Collection
    {
        return Restaurant::where('is_active', true)
            ->with(['cuisines', 'tags'])
            ->get()
            ->map(fn (Restaurant $restaurant) => $restaurant->toRecommendationArray());
    }
}
