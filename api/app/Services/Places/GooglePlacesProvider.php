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

    private const DETAILS_ENDPOINT = 'https://places.googleapis.com/v1/places';

    /** Only request the fields PlaceNormalizer actually consumes — avoids pricier response tiers. */
    private const FIELD_MASK = 'places.id,places.displayName,places.location,places.types,places.rating,places.priceLevel,places.currentOpeningHours.openNow';

    /**
     * Winner-only presentation fields (photos/reviews), fetched fresh per request —
     * never persisted (Google's terms don't allow caching photo names/review content
     * beyond the Place ID itself). This call is on a pricier tier than nearbyRestaurants(),
     * which is exactly why it only ever runs for the one chosen restaurant, not every candidate.
     */
    private const DETAILS_FIELD_MASK = 'photos,reviews,googleMapsUri,currentOpeningHours,utcOffsetMinutes';

    public function __construct(private readonly ?string $apiKey) {}

    public function nearbyRestaurants(float $latitude, float $longitude, float $radiusKm): Collection
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException('PLACES_PROVIDER=google requires GOOGLE_PLACES_API_KEY to be set.');
        }

        $response = Http::withHeaders([
            'X-Goog-Api-Key' => $this->apiKey,
            'X-Goog-FieldMask' => self::FIELD_MASK,
        ])->timeout(8)->post(self::ENDPOINT, [
            'includedTypes' => ['restaurant'],
            'maxResultCount' => 20,
            'locationRestriction' => [
                'circle' => [
                    'center' => ['latitude' => $latitude, 'longitude' => $longitude],
                    'radius' => $radiusKm * 1000,
                ],
            ],
        ])->throw();

        $places = $response->json('places', []);

        return collect($places)->map(fn (array $place) => new ProviderPlace(
            providerPlaceId: $place['id'],
            name: $place['displayName']['text'] ?? 'Unknown',
            latitude: $place['location']['latitude'],
            longitude: $place['location']['longitude'],
            types: $place['types'] ?? [],
            rating: $place['rating'] ?? null,
            priceLevel: $this->mapPriceLevel($place['priceLevel'] ?? null),
            openNow: $place['currentOpeningHours']['openNow'] ?? null,
        ));
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
