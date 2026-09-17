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
use App\Support\DiscoveryMode;
use App\Support\Vibe;
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
    /** Nearby-Search types requested for Cafe/Low-key mode — reaches small restaurants, cafés, bakeries, dessert shops alike, not just literal cafés. */
    private const CAFE_LEANING_TYPES = ['restaurant', 'cafe', 'coffee_shop', 'bakery'];

    public function __construct(private readonly PlaceNormalizer $normalizer) {}

    /** Set by nearbyRestaurants() when a craving is present, for RecommendationController's debug payload only. */
    private array $lastCandidateCounts = [];

    /**
     * @return array<int, array<string, mixed>> normalized restaurant arrays, shaped for RecommendationService
     */
    public function nearbyRestaurants(
        float $latitude, float $longitude, float $radiusKm,
        ?CravingIntent $craving = null, ?DiscoveryMode $mode = null, ?Vibe $vibe = null,
    ): array {
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

        $includedTypes = $this->includedTypesFor($mode);

        if (! $this->isAreaCovered($latitude, $longitude, $radiusKm, $includedTypes)) {
            $this->syncFromGoogle($latitude, $longitude, $radiusKm, $apiKey, $includedTypes);
        }

        $nearbyCount = null;
        $textSearchCount = 0;
        if ($craving !== null && $craving->primarySearchTerm() !== null) {
            $nearbyCount = count($this->readGoogleRestaurantsNear($latitude, $longitude, $radiusKm, $includedTypes));

            // Text Search is an opportunistic boost, not a required part of the response — a
            // failure here (Google rate limit, transient 5xx, bad query) must never turn an
            // otherwise-working nearby-search result into a hard failure for the whole request.
            try {
                $cravingDiscriminator = $craving->concept
                    ?? strtolower(trim(preg_replace('/\s+/', ' ', $craving->raw) ?? ''));
                // The concept's specific Google type when FoodTaxonomy has one (ice cream ->
                // ice_cream_shop, dessert -> dessert_restaurant) — narrower than 'restaurant'
                // without reopening the "craving search goes unrestricted" bug this replaces.
                // Falls back to 'restaurant' for dish concepts with no placeTypes entry and for
                // unresolved/raw-text cravings, i.e. today's exact prior behavior.
                $cravingIncludedType = $craving->placeTypes[0] ?? 'restaurant';
                $textSearchCount = $this->syncFromTextSearchQuery(
                    $latitude, $longitude, $radiusKm, $apiKey,
                    $craving->primarySearchTerm(), 'craving:'.$cravingDiscriminator, $cravingIncludedType
                );
            } catch (Throwable $e) {
                Log::warning('Craving text search failed, continuing with nearby-only results', [
                    'error' => $e->getMessage(),
                    'query' => $craving->primarySearchTerm(),
                ]);
            }
        }

        // Unrestricted (no includedType) — Google's Text Search only accepts one type, and a
        // lane meant to catch cafe-or-coffee_shop-or-bakery can't be expressed as a single
        // restriction anyway, so narrowing it here would just silently drop valid matches.
        foreach ($this->discoveryTextQueries($mode, $vibe) as $discoveryQuery) {
            try {
                $textSearchCount += $this->syncFromTextSearchQuery(
                    $latitude, $longitude, $radiusKm, $apiKey, $discoveryQuery, 'discovery:'.$discoveryQuery, null
                );
            } catch (Throwable $e) {
                Log::warning('Discovery text search failed, continuing without it', [
                    'error' => $e->getMessage(),
                    'query' => $discoveryQuery,
                ]);
            }
        }

        $restaurants = $this->readGoogleRestaurantsNear($latitude, $longitude, $radiusKm, $includedTypes);

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

    /** @return string[] */
    private function includedTypesFor(?DiscoveryMode $mode): array
    {
        return match ($mode) {
            DiscoveryMode::Cafe, DiscoveryMode::LowKey => self::CAFE_LEANING_TYPES,
            default => ['restaurant'],
        };
    }

    /** @return string[] */
    private function discoveryTextQueries(?DiscoveryMode $mode, ?Vibe $vibe): array
    {
        $queries = [];

        if ($mode === DiscoveryMode::LowKey) {
            $queries = array_merge($queries, Config::get('recommendation.discovery_queries.low_key', []));
        } elseif ($mode === DiscoveryMode::Cafe) {
            $queries = array_merge($queries, Config::get('recommendation.discovery_queries.cafe', []));
        }

        if ($vibe !== null) {
            $queries = array_merge($queries, Config::get('recommendation.vibe_queries.'.$vibe->value, []));
        }

        return array_values(array_unique($queries));
    }

    /**
     * @param  string[]  $requiredTypes
     */
    private function isAreaCovered(float $latitude, float $longitude, float $radiusKm, array $requiredTypes): bool
    {
        $cacheHours = (int) Config::get('services.places.cache_hours', 24);
        $cutoff = now()->subHours($cacheHours);

        return PlaceSyncArea::where('provider', 'google')
            ->where('synced_at', '>=', $cutoff)
            ->get()
            ->contains(function (PlaceSyncArea $area) use ($latitude, $longitude, $radiusKm, $requiredTypes) {
                $distanceToCenter = RecommendationService::distanceKm(
                    (float) $area->latitude, (float) $area->longitude, $latitude, $longitude
                );

                // Covered only if the requested search circle sits fully inside the already-synced
                // one AND that sync already fetched every type this request needs (a superset)  —
                // e.g. a Cafe-mode sync covers a later Normal request, but not vice versa.
                $locationCovered = $distanceToCenter + $radiusKm <= (float) $area->radius_km;
                $typesCovered = empty(array_diff($requiredTypes, $area->types ?? []));

                return $locationCovered && $typesCovered;
            });
    }

    /**
     * @param  string[]  $includedTypes
     */
    private function syncFromGoogle(float $latitude, float $longitude, float $radiusKm, string $apiKey, array $includedTypes): void
    {
        $providerPlaces = (new GooglePlacesProvider($apiKey))->nearbyRestaurants($latitude, $longitude, $radiusKm, $includedTypes);
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
            'types' => $includedTypes,
        ]);
    }

    /**
     * One Google Text Search call per distinct query. The result set itself is cached (not just
     * the resolved query) so a cluster of nearby users requesting the same thing in the same
     * window doesn't multiply Google billing; still upserted on every call — cache hit or miss —
     * so scoring always reads fresh local data, only the Google call itself is skipped.
     *
     * @return int number of places returned by this search (cache hit or miss)
     */
    private function syncFromTextSearchQuery(
        float $latitude, float $longitude, float $radiusKm, string $apiKey, string $query,
        string $cacheDiscriminator, ?string $includedType,
    ): int {
        $normalized = Cache::remember(
            // includedType is part of the cache key — the same query text restricted to
            // 'restaurant' vs unrestricted are different requests, must not share a cache entry.
            $this->textSearchCacheKey($latitude, $longitude, $radiusKm, $cacheDiscriminator.':'.($includedType ?? 'any')),
            now()->addMinutes(60),
            function () use ($apiKey, $query, $latitude, $longitude, $radiusKm, $includedType) {
                $providerPlaces = (new GooglePlacesProvider($apiKey))->searchText($query, $latitude, $longitude, $radiusKm, $includedType);

                return $this->normalizer->normalize($providerPlaces)->all();
            }
        );

        foreach ($normalized as $data) {
            $this->upsertRestaurant($data);
        }

        return count($normalized);
    }

    /** Rounded to ~100m/1km buckets so nearby duplicate searches share a cache entry. */
    private function textSearchCacheKey(float $latitude, float $longitude, float $radiusKm, string $discriminator): string
    {
        return sprintf(
            'places_text:v1:%s:%s:%d:%s',
            round($latitude, 3), round($longitude, 3), max(1, (int) round($radiusKm)), $discriminator
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
                'user_rating_count' => $data['user_rating_count'] ?? null,
                'google_types' => $data['google_types'] ?? [],
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

    /**
     * $includedTypes is what the CURRENT request asked Google for (includedTypesFor($mode)) —
     * not a literal tag to intersect against. Google's Nearby/Text Search `includedTypes` param
     * matches a broad category server-side (e.g. `restaurant` matches a place whose own specific
     * type is `hamburger_restaurant` or `ramen_restaurant`), but the *returned* `types[]` on that
     * place is its own specific subtype, which usually never literally contains the string
     * `restaurant` — so requiring an intersection with $includedTypes would wrongly exclude
     * almost every legitimately-matched restaurant (this was caught by PlacesServiceCravingTest
     * failing on a plain 'hamburger_restaurant' candidate).
     *
     * Instead this only excludes what the leak bug actually was: a place typed purely as one of
     * CAFE_LEANING_TYPES's cafe-specific additions (cafe/coffee_shop/bakery) showing up in a mode
     * that never asked for those types. A row with no recorded google_types (synced before that
     * column existed) is always treated as compatible.
     */
    private function readGoogleRestaurantsNear(float $latitude, float $longitude, float $radiusKm, array $includedTypes): array
    {
        $cafeOnlyTypes = array_diff(self::CAFE_LEANING_TYPES, ['restaurant']);
        $modeAllowsCafeLeaning = ! empty(array_intersect($includedTypes, $cafeOnlyTypes));

        return Restaurant::where('provider', 'google')
            ->where('is_active', true)
            ->with(['cuisines', 'tags'])
            ->get()
            ->filter(fn (Restaurant $restaurant) => RecommendationService::distanceKm(
                $latitude, $longitude, (float) $restaurant->latitude, (float) $restaurant->longitude
            ) <= $radiusKm)
            ->filter(fn (Restaurant $restaurant) => $modeAllowsCafeLeaning
                || empty($restaurant->google_types)
                || empty(array_intersect($restaurant->google_types, $cafeOnlyTypes)))
            ->map(fn (Restaurant $restaurant) => $restaurant->toRecommendationArray())
            ->values()
            ->all();
    }
}
