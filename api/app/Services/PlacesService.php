<?php

namespace App\Services;

use App\Models\Cuisine;
use App\Models\PlaceSyncArea;
use App\Models\Restaurant;
use App\Models\Tag;
use App\Services\Craving\CravingIntent;
use App\Services\Places\FixturePlacesProvider;
use App\Services\Places\GooglePlacesProvider;
use App\Services\Places\PlaceNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Orchestrates provider selection, area-based caching, and persistence.
 * Providers only fetch; this is the only place that decides whether to
 * hit the network and the only place that writes to the database.
 */
class PlacesService
{
    public function __construct(private readonly PlaceNormalizer $normalizer) {}

    /** Set by nearbyRestaurants() when a craving is present, for RecommendationController's debug payload only. */
    private array $lastCandidateCounts = [];

    /**
     * @return array<int, array<string, mixed>> normalized restaurant arrays, shaped for RecommendationService
     */
    public function nearbyRestaurants(float $latitude, float $longitude, float $radiusKm, ?CravingIntent $craving = null): array
    {
        $provider = Config::get('services.places.provider', 'fixture');

        if ($provider === 'fixture') {
            return (new FixturePlacesProvider)->nearbyRestaurants($latitude, $longitude, $radiusKm)->all();
        }

        if ($provider !== 'google') {
            throw new RuntimeException("Unknown PLACES_PROVIDER [{$provider}].");
        }

        $apiKey = Config::get('services.places.google_api_key');
        if (empty($apiKey)) {
            throw new RuntimeException('PLACES_PROVIDER=google requires GOOGLE_PLACES_API_KEY to be set.');
        }

        if (! $this->isAreaCovered($latitude, $longitude, $radiusKm)) {
            $this->syncFromGoogle($latitude, $longitude, $radiusKm, $apiKey);
        }

        $nearbyCount = null;
        $textSearchCount = 0;
        if ($craving !== null && $craving->primarySearchTerm() !== null) {
            $nearbyCount = count($this->readGoogleRestaurantsNear($latitude, $longitude, $radiusKm));

            // Text Search is an opportunistic boost, not a required part of the response — a
            // failure here (Google rate limit, transient 5xx, bad query) must never turn an
            // otherwise-working nearby-search result into a hard failure for the whole request.
            try {
                $textSearchCount = $this->syncFromTextSearch($latitude, $longitude, $radiusKm, $apiKey, $craving);
            } catch (Throwable $e) {
                Log::warning('Craving text search failed, continuing with nearby-only results', [
                    'error' => $e->getMessage(),
                    'query' => $craving->primarySearchTerm(),
                ]);
            }
        }

        $restaurants = $this->readGoogleRestaurantsNear($latitude, $longitude, $radiusKm);

        $this->lastCandidateCounts = $nearbyCount === null ? [] : [
            'nearby' => $nearbyCount,
            'textSearch' => $textSearchCount,
            'merged' => count($restaurants),
        ];

        return $restaurants;
    }

    /**
     * Debug-only: candidate counts from the most recent nearbyRestaurants() call that included
     * a craving. Empty when no craving was resolved (i.e. no text search ran). Never used by
     * scoring — purely for RecommendationController's optional debug payload.
     *
     * @return array{nearby: int, textSearch: int, merged: int}|array{}
     */
    public function lastCandidateCounts(): array
    {
        return $this->lastCandidateCounts;
    }

    private function isAreaCovered(float $latitude, float $longitude, float $radiusKm): bool
    {
        $cacheHours = (int) Config::get('services.places.cache_hours', 24);
        $cutoff = now()->subHours($cacheHours);

        return PlaceSyncArea::where('provider', 'google')
            ->where('synced_at', '>=', $cutoff)
            ->get()
            ->contains(function (PlaceSyncArea $area) use ($latitude, $longitude, $radiusKm) {
                $distanceToCenter = RecommendationService::distanceKm(
                    (float) $area->latitude, (float) $area->longitude, $latitude, $longitude
                );

                // Covered only if the requested search circle sits fully inside the already-synced one.
                return $distanceToCenter + $radiusKm <= (float) $area->radius_km;
            });
    }

    private function syncFromGoogle(float $latitude, float $longitude, float $radiusKm, string $apiKey): void
    {
        $providerPlaces = (new GooglePlacesProvider($apiKey))->nearbyRestaurants($latitude, $longitude, $radiusKm);
        $normalized = $this->normalizer->normalize($providerPlaces);

        foreach ($normalized as $data) {
            $this->upsertRestaurant($data);
        }

        PlaceSyncArea::create([
            'provider' => 'google',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius_km' => $radiusKm,
            'synced_at' => now(),
        ]);
    }

    /**
     * At most one Google Text Search call per recommendation request — $craving->primarySearchTerm()
     * is the single term used, never a loop over every alias. The result set itself is cached
     * (not just the AI-parsed intent) so a cluster of nearby users searching the same thing in
     * the same window doesn't multiply Google billing; still upserted on every call — cache hit
     * or miss — so scoring always reads fresh local data, only the Google call itself is skipped.
     *
     * @return int number of places returned by this search (cache hit or miss)
     */
    private function syncFromTextSearch(float $latitude, float $longitude, float $radiusKm, string $apiKey, CravingIntent $craving): int
    {
        $query = $craving->primarySearchTerm();
        if ($query === null) {
            return 0;
        }

        $normalized = Cache::remember(
            $this->textSearchCacheKey($latitude, $longitude, $radiusKm, $craving),
            now()->addMinutes(60),
            function () use ($apiKey, $query, $latitude, $longitude, $radiusKm) {
                $providerPlaces = (new GooglePlacesProvider($apiKey))->searchText($query, $latitude, $longitude, $radiusKm);

                return $this->normalizer->normalize($providerPlaces)->all();
            }
        );

        foreach ($normalized as $data) {
            $this->upsertRestaurant($data);
        }

        return count($normalized);
    }

    /** Rounded to ~100m/1km buckets so nearby duplicate searches share a cache entry. */
    private function textSearchCacheKey(float $latitude, float $longitude, float $radiusKm, CravingIntent $craving): string
    {
        $concept = $craving->concept ?? strtolower(trim(preg_replace('/\s+/', ' ', $craving->raw) ?? ''));

        return sprintf(
            'places_text:v1:%s:%s:%d:%s',
            round($latitude, 3), round($longitude, 3), max(1, (int) round($radiusKm)), $concept
        );
    }

    private function upsertRestaurant(array $data): void
    {
        $restaurant = Restaurant::updateOrCreate(
            ['provider' => 'google', 'provider_place_id' => $data['provider_place_id']],
            [
                'name' => $data['name'],
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'price_level' => $data['price_level'],
                'rating' => $data['rating'],
                'is_active' => $data['is_active'],
                'opening_hours' => $data['opening_hours'],
                'food_category' => $data['food_category'],
                'last_synced_at' => now(),
            ]
        );

        $cuisineIds = collect($data['cuisines'])->map(
            fn (string $slug) => Cuisine::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)])->id
        );
        $restaurant->cuisines()->sync($cuisineIds);

        $tagIds = collect($data['tags'])->map(
            fn (string $name) => Tag::firstOrCreate(['name' => $name])->id
        );
        $restaurant->tags()->sync($tagIds);
    }

    private function readGoogleRestaurantsNear(float $latitude, float $longitude, float $radiusKm): array
    {
        return Restaurant::where('provider', 'google')
            ->where('is_active', true)
            ->with(['cuisines', 'tags'])
            ->get()
            ->filter(fn (Restaurant $restaurant) => RecommendationService::distanceKm(
                $latitude, $longitude, (float) $restaurant->latitude, (float) $restaurant->longitude
            ) <= $radiusKm)
            ->map(fn (Restaurant $restaurant) => $restaurant->toRecommendationArray())
            ->values()
            ->all();
    }
}
