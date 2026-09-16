<?php

namespace App\Services\Places;

use Illuminate\Support\Collection;

interface PlacesProvider
{
    /**
     * Google-backed providers return Collection<ProviderPlace> (raw, unnormalized).
     * The fixture provider returns Collection<array> already shaped for
     * RecommendationService, since it reads already-normalized Eloquent rows.
     */
    public function nearbyRestaurants(float $latitude, float $longitude, float $radiusKm): Collection;
}
