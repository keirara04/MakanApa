<?php

namespace App\Services\Places;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Only knows how to talk to Google Places (New) :searchNearby. Does not
 * normalize into MakanApa semantics (PlaceNormalizer's job) and does not
 * touch the database (PlacesService's job).
 */
class GooglePlacesProvider implements PlacesProvider
{
    private const ENDPOINT = 'https://places.googleapis.com/v1/places:searchNearby';

    private const TEXT_SEARCH_ENDPOINT = 'https://places.googleapis.com/v1/places:searchText';

    private const DETAILS_ENDPOINT = 'https://places.googleapis.com/v1/places';

    /** Only request the fields PlaceNormalizer actually consumes — avoids pricier response tiers. */
    private const FIELD_MASK = 'places.id,places.displayName,places.location,places.types,places.rating,places.priceLevel,places.currentOpeningHours.openNow,places.userRatingCount';

    /**
     * Winner-only presentation fields (photos/reviews), fetched fresh per request —
     * never persisted (Google's terms don't allow caching photo names/review content
     * beyond the Place ID itself). This call is on a pricier tier than nearbyRestaurants(),
     * which is exactly why it only ever runs for the one chosen restaurant, not every candidate.
     */
    private const DETAILS_FIELD_MASK = 'photos,reviews,googleMapsUri,currentOpeningHours,utcOffsetMinutes';

    /** Same fields as FIELD_MASK, but the single-place GET endpoint (fetchPlace()) doesn't use the `places.` list-response prefix. */
    private const SINGLE_PLACE_FIELD_MASK = 'id,displayName,location,types,rating,priceLevel,currentOpeningHours.openNow,userRatingCount';

    public function __construct(private readonly ?string $apiKey) {}

    public function nearbyRestaurants(float $latitude, float $longitude, float $radiusKm, array $includedTypes = ['restaurant']): Collection
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException('PLACES_PROVIDER=google requires GOOGLE_PLACES_API_KEY to be set.');
        }

        $response = Http::withHeaders([
            'X-Goog-Api-Key' => $this->apiKey,
            'X-Goog-FieldMask' => self::FIELD_MASK,
        ])->timeout(8)->post(self::ENDPOINT, [
            'includedTypes' => $includedTypes,
            'maxResultCount' => 20,
            // Nearby Search (New) caps results at 20 regardless of radius and offers no
            // pagination — DISTANCE ranking means a wide/tiled search's 20-result budget spreads
            // across the whole circle instead of clustering on whatever's most "popular" near the
            // center, which is what actually made tiling worthwhile in the first place.
            'rankPreference' => 'DISTANCE',
            'locationRestriction' => [
                'circle' => [
                    'center' => ['latitude' => $latitude, 'longitude' => $longitude],
                    'radius' => $radiusKm * 1000,
                ],
            ],
        ])->throw();

        return collect($response->json('places', []))->map(fn (array $place) => $this->mapPlace($place));
    }

    /**
     * Text Search — used for both craving lookups and discovery (Low-key/Cafe/Vibe) lanes, at
     * most once per query per recommendation request. Unlike nearbyRestaurants(), Google's
     * locationBias here is a soft hint, not a hard filter, so callers must still distance-filter
     * the results themselves.
     *
     * $includedType is a single Google type (Text Search doesn't accept an array like Nearby
     * Search does) or null for unrestricted. Callers decide it — a craving with a known
     * FoodTaxonomy placeType passes that (e.g. `ice_cream_shop`), a craving without one falls
     * back to `restaurant`, and a discovery lane meant to catch cafe-or-coffee_shop-or-bakery
     * passes null since no single type covers all three.
     */
    public function searchText(string $query, float $latitude, float $longitude, float $radiusKm, ?string $includedType = 'restaurant'): Collection
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException('PLACES_PROVIDER=google requires GOOGLE_PLACES_API_KEY to be set.');
        }

        $payload = [
            'textQuery' => $query,
            'locationBias' => [
                'circle' => [
                    'center' => ['latitude' => $latitude, 'longitude' => $longitude],
                    'radius' => $radiusKm * 1000,
                ],
            ],
        ];
        if ($includedType !== null) {
            $payload['includedType'] = $includedType;
        }

        $response = Http::withHeaders([
            'X-Goog-Api-Key' => $this->apiKey,
            'X-Goog-FieldMask' => self::FIELD_MASK,
        ])->timeout(8)->post(self::TEXT_SEARCH_ENDPOINT, $payload)->throw();

        return collect($response->json('places', []))->map(fn (array $place) => $this->mapPlace($place));
    }

    private function mapPlace(array $place): ProviderPlace
    {
        return new ProviderPlace(
            providerPlaceId: $place['id'],
            name: $place['displayName']['text'] ?? 'Unknown',
            latitude: $place['location']['latitude'],
            longitude: $place['location']['longitude'],
            types: $place['types'] ?? [],
            rating: $place['rating'] ?? null,
            priceLevel: $this->mapPriceLevel($place['priceLevel'] ?? null),
            openNow: $place['currentOpeningHours']['openNow'] ?? null,
            userRatingCount: $place['userRatingCount'] ?? null,
        );
    }

    /**
     * Single place by its provider ID, in the same shape nearbyRestaurants()/searchText() return
     * (candidate fields only, via FIELD_MASK) — used to resolve a google_fallback search result
     * into a normalizable place once the user actually picks it, not for the pricier
     * photos/reviews tier (that's fetchPresentationDetails() below).
     */
    public function fetchPlace(string $providerPlaceId): ProviderPlace
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException('PLACES_PROVIDER=google requires GOOGLE_PLACES_API_KEY to be set.');
        }

        $response = Http::withHeaders([
            'X-Goog-Api-Key' => $this->apiKey,
            'X-Goog-FieldMask' => self::SINGLE_PLACE_FIELD_MASK,
        ])->timeout(8)->get(self::DETAILS_ENDPOINT."/{$providerPlaceId}")->throw();

        return $this->mapPlace($response->json());
    }

    /**
     * Raw decoded JSON — normalization into transient DTOs happens in
     * PlaceNormalizer::normalizePresentationDetails(), not here.
     */
    public function fetchPresentationDetails(string $providerPlaceId): array
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException('PLACES_PROVIDER=google requires GOOGLE_PLACES_API_KEY to be set.');
        }

        $response = Http::withHeaders([
            'X-Goog-Api-Key' => $this->apiKey,
            'X-Goog-FieldMask' => self::DETAILS_FIELD_MASK,
        ])->timeout(8)->get(self::DETAILS_ENDPOINT."/{$providerPlaceId}")->throw();

        return $response->json();
    }

    private function mapPriceLevel(?string $googlePriceLevel): ?int
    {
        return match ($googlePriceLevel) {
            'PRICE_LEVEL_INEXPENSIVE' => 1,
            'PRICE_LEVEL_MODERATE' => 2,
            'PRICE_LEVEL_EXPENSIVE', 'PRICE_LEVEL_VERY_EXPENSIVE' => 3,
            default => null,
        };
    }
}
