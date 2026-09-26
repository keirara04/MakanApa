<?php

namespace App\Services\Places;

use App\Models\ApiUsageDaily;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Only knows how to talk to Google Places (New) :searchNearby. Does not
 * normalize into MakanApa semantics (PlaceNormalizer's job) and does not
 * touch the database (PlacesService's job) — apart from counting each billable call it makes
 * (ApiUsageDaily, one key per SKU below) for the admin API cost page.
 */
class GooglePlacesProvider implements PlacesProvider
{
    private const ENDPOINT = 'https://places.googleapis.com/v1/places:searchNearby';

    private const TEXT_SEARCH_ENDPOINT = 'https://places.googleapis.com/v1/places:searchText';

    private const DETAILS_ENDPOINT = 'https://places.googleapis.com/v1/places';

    /** Usage keys — one per billed SKU, priced in config/admin_budgets.php. */
    public const USAGE_NEARBY_SEARCH = 'nearby_search';

    public const USAGE_TEXT_SEARCH = 'text_search';

    public const USAGE_PLACE_DETAILS = 'place_details';

    public const USAGE_PLACE_DETAILS_ATMOSPHERE = 'place_details_atmosphere';

    public const USAGE_PLACE_PHOTO = 'place_photo';

    /**
     * Only request the fields PlaceNormalizer actually consumes — avoids pricier response tiers.
     * regularOpeningHours.periods + utcOffsetMinutes let Restaurant::openStatus() work out
     * open/closed at read time instead of trusting an openNow snapshot up to a day old; they sit
     * in the same SKU tier currentOpeningHours.openNow already pulls in. shortFormattedAddress
     * ("Jalan Reko, Kajang") is what tells two branches of the same chain apart in search.
     */
    private const FIELD_MASK = 'places.id,places.displayName,places.location,places.types,places.rating,places.priceLevel,places.currentOpeningHours.openNow,places.regularOpeningHours.periods,places.utcOffsetMinutes,places.userRatingCount,places.shortFormattedAddress';

    /**
     * Winner-only presentation fields (photos/reviews), fetched fresh per request —
     * never persisted (Google's terms don't allow caching photo names/review content
     * beyond the Place ID itself). This call is on a pricier tier than nearbyRestaurants(),
     * which is exactly why it only ever runs for the one chosen restaurant, not every candidate.
     */
    private const DETAILS_FIELD_MASK = 'photos,reviews,googleMapsUri,currentOpeningHours,utcOffsetMinutes';

    /** Same fields as FIELD_MASK, but the single-place GET endpoint (fetchPlace()) doesn't use the `places.` list-response prefix. */
    private const SINGLE_PLACE_FIELD_MASK = 'id,displayName,location,types,rating,priceLevel,currentOpeningHours.openNow,regularOpeningHours.periods,utcOffsetMinutes,userRatingCount,shortFormattedAddress';

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
        ]);
        self::recordUsage(self::USAGE_NEARBY_SEARCH);
        $response->throw();

        return collect($response->json('places', []))->map(fn (array $place) => $this->mapPlace($place));
    }

    /**
     * Same circles as nearbyRestaurants(), fired concurrently via Http::pool instead of one
     * at a time — a cold-cache area with several tiles (PlacesService::MAX_TILES, up to 7)
     * used to pay the sum of each Nearby Search's latency sequentially; this pays roughly the
     * slowest single call instead. That sequential wait was the main source of "Search this
     * area" feeling laggy the first time someone browses a new part of the map.
     *
     * @param  array<int, array{lat: float, lon: float, radius: float}>  $tiles
     * @param  string[]  $includedTypes
     * @return array<int, Collection<int, ProviderPlace>> same order/index as $tiles
     */
    public function nearbyRestaurantsBatch(array $tiles, array $includedTypes = ['restaurant']): array
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException('PLACES_PROVIDER=google requires GOOGLE_PLACES_API_KEY to be set.');
        }

        if (count($tiles) === 1) {
            return [$this->nearbyRestaurants($tiles[0]['lat'], $tiles[0]['lon'], $tiles[0]['radius'], $includedTypes)];
        }

        $responses = Http::pool(fn (Pool $pool) => collect($tiles)->map(
            fn (array $tile, int $index) => $pool->as((string) $index)
                ->withHeaders([
                    'X-Goog-Api-Key' => $this->apiKey,
                    'X-Goog-FieldMask' => self::FIELD_MASK,
                ])
                ->timeout(8)
                ->post(self::ENDPOINT, [
                    'includedTypes' => $includedTypes,
                    'maxResultCount' => 20,
                    'rankPreference' => 'DISTANCE',
                    'locationRestriction' => [
                        'circle' => [
                            'center' => ['latitude' => $tile['lat'], 'longitude' => $tile['lon']],
                            'radius' => $tile['radius'] * 1000,
                        ],
                    ],
                ])
        )->all());
        self::recordUsage(self::USAGE_NEARBY_SEARCH, self::answered($responses));

        return collect($tiles)
            ->map(function (array $tile, int $index) use ($responses) {
                // A pooled request that never connected comes back as the exception itself, not
                // a Response — rethrow it rather than calling throw() on it.
                $response = $responses[(string) $index];
                if ($response instanceof Throwable) {
                    throw $response;
                }

                return collect($response->throw()->json('places', []))->map(fn (array $place) => $this->mapPlace($place));
            })
            ->values()
            ->all();
    }

    /**
     * Several Text Search lanes fired concurrently. Unlike nearbyRestaurantsBatch(), one lane
     * failing never fails the others — each slot is either its places or the Throwable that
     * lane hit, so the caller can log and skip just that lane.
     *
     * @param  array<int, array{query: string, includedType: ?string}>  $lanes
     * @return array<int, Collection<int, ProviderPlace>|Throwable> same order/index as $lanes
     */
    public function searchTextBatch(array $lanes, float $latitude, float $longitude, float $radiusKm): array
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException('PLACES_PROVIDER=google requires GOOGLE_PLACES_API_KEY to be set.');
        }

        if (count($lanes) === 1) {
            try {
                return [$this->searchText($lanes[0]['query'], $latitude, $longitude, $radiusKm, $lanes[0]['includedType'])];
            } catch (Throwable $e) {
                return [$e];
            }
        }

        $responses = Http::pool(fn (Pool $pool) => collect($lanes)->map(
            fn (array $lane, int $index) => $pool->as((string) $index)
                ->withHeaders([
                    'X-Goog-Api-Key' => $this->apiKey,
                    'X-Goog-FieldMask' => self::FIELD_MASK,
                ])
                ->timeout(8)
                ->post(self::TEXT_SEARCH_ENDPOINT, $this->textSearchPayload($lane['query'], $latitude, $longitude, $radiusKm, $lane['includedType']))
        )->all());
        self::recordUsage(self::USAGE_TEXT_SEARCH, self::answered($responses));

        return collect($lanes)
            ->map(function (array $lane, int $index) use ($responses) {
                $response = $responses[(string) $index];
                if ($response instanceof Throwable) {
                    return $response;
                }

                try {
                    return collect($response->throw()->json('places', []))->map(fn (array $place) => $this->mapPlace($place));
                } catch (Throwable $e) {
                    return $e;
                }
            })
            ->values()
            ->all();
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

        $response = Http::withHeaders([
            'X-Goog-Api-Key' => $this->apiKey,
            'X-Goog-FieldMask' => self::FIELD_MASK,
        ])->timeout(8)->post(self::TEXT_SEARCH_ENDPOINT, $this->textSearchPayload($query, $latitude, $longitude, $radiusKm, $includedType));
        self::recordUsage(self::USAGE_TEXT_SEARCH);
        $response->throw();

        return collect($response->json('places', []))->map(fn (array $place) => $this->mapPlace($place));
    }

    /** @return array<string, mixed> */
    private function textSearchPayload(string $query, float $latitude, float $longitude, float $radiusKm, ?string $includedType): array
    {
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

        return $payload;
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
            openingPeriods: $place['regularOpeningHours']['periods'] ?? null,
            utcOffsetMinutes: $place['utcOffsetMinutes'] ?? null,
            address: $place['shortFormattedAddress'] ?? null,
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
        ])->timeout(8)->get(self::DETAILS_ENDPOINT."/{$providerPlaceId}");
        self::recordUsage(self::USAGE_PLACE_DETAILS);
        $response->throw();

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
        ])->timeout(8)->get(self::DETAILS_ENDPOINT."/{$providerPlaceId}");
        self::recordUsage(self::USAGE_PLACE_DETAILS_ATMOSPHERE);
        $response->throw();

        return $response->json();
    }

    /**
     * Counted once Google has answered (any status) — a call that never connected isn't billed.
     * Public so other Places callers (the photo proxy) count against the same SKUs.
     */
    public static function recordUsage(string $endpoint, int $calls = 1): void
    {
        ApiUsageDaily::record(ApiUsageDaily::PROVIDER_GOOGLE_PLACES, $endpoint, $calls);
    }

    /** @param  array<string, Response|Throwable>  $responses */
    private static function answered(array $responses): int
    {
        return count(array_filter($responses, fn ($response) => $response instanceof Response));
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
