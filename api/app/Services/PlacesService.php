<?php

namespace App\Services;

use App\Models\Cuisine;
use App\Models\PlaceSyncArea;
use App\Models\Restaurant;
use App\Models\RestaurantFieldOverride;
use App\Models\Tag;
use App\Services\Craving\CravingIntent;
use App\Services\Places\FixturePlacesProvider;
use App\Services\Places\GooglePlacesProvider;
use App\Services\Places\PlaceNormalizer;
use App\Services\Places\ProviderPlace;
use App\Support\DiscoveryMode;
use App\Support\Vibe;
use Illuminate\Support\Arr;
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
    /**
     * Default Nearby-Search types for every mode — 'restaurant' alone excludes real food places
     * Google categorizes separately (a takeaway counter, a food court stall, delivery-only
     * kitchens). Cafe/Low-key mode adds CAFE_EXTRA_TYPES on top of this same base.
     */
    private const BASE_TYPES = ['restaurant', 'meal_takeaway', 'meal_delivery', 'food_court'];

    /** The literal extra breadth Cafe/Low-key mode reaches for — also what readGoogleRestaurantsNear() excludes from every other mode, to stop these from leaking in (see its own doc comment). */
    private const CAFE_EXTRA_TYPES = ['cafe', 'coffee_shop', 'bakery'];

    /** Above this radius, a single Nearby Search's 20-result cap starts leaving real coverage gaps — see syncFromGoogle()'s tiling. */
    private const TILE_RADIUS_THRESHOLD_KM = 2.5;

    /** Hard cap on tiles per request, regardless of how large radiusKm is — bounds Google API cost from one client request. */
    private const MAX_TILES = 7;

    /** searchPlaces()'s radius around the caller's Nearby browse center — a fixed, generous "somewhere in the area" scope, not the viewport-derived radius nearbyRestaurants() uses. */
    private const SEARCH_RADIUS_KM = 6.0;

    /** Below this many local matches, searchPlaces() also asks Google — keeps MakanApa's own data first-class by construction (it's always searched, always returned first) rather than by rank-boosting alone, while still calling Google on every keystroke when our own data already has enough to show. */
    private const GOOGLE_FALLBACK_MIN_LOCAL_RESULTS = 8;

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
            return array_merge(
                (new FixturePlacesProvider)->nearbyRestaurants($latitude, $longitude, $radiusKm)->all(),
                $this->readUserSubmittedRestaurantsNear($latitude, $longitude, $radiusKm)
            );
        }

        if ($provider !== 'google') {
            throw new RuntimeException("Unknown PLACES_PROVIDER [{$provider}].");
        }

        $apiKey = Config::get('services.places.google_api_key');
        if (empty($apiKey)) {
            throw new RuntimeException('PLACES_PROVIDER=google requires GOOGLE_PLACES_API_KEY to be set.');
        }

        $includedTypes = $this->includedTypesFor($mode);

        // Nearby Search (New) caps results at 20 per call with no pagination — for a wide search
        // (Decide's 5km "Don't mind," a zoomed-out Nearby viewport) one circle silently truncates
        // real coverage. Splitting into overlapping sub-circles and merging (upsertRestaurant()'s
        // existing dedupe-by-provider_place_id handles the overlap) surfaces more of what's
        // actually there. Small/typical searches are untouched — tileCircles() returns a single
        // tile below TILE_RADIUS_THRESHOLD_KM, identical to pre-tiling behavior.
        foreach ($this->tileCircles($latitude, $longitude, $radiusKm) as $tile) {
            if (! $this->isAreaCovered($tile['lat'], $tile['lon'], $tile['radius'], $includedTypes)) {
                $this->syncFromGoogle($tile['lat'], $tile['lon'], $tile['radius'], $apiKey, $includedTypes);
            }
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

        $restaurants = array_merge(
            $this->readGoogleRestaurantsNear($latitude, $longitude, $radiusKm, $includedTypes),
            $this->readUserSubmittedRestaurantsNear($latitude, $longitude, $radiusKm)
        );

        $this->lastCandidateCounts = $nearbyCount === null ? [] : [
            'nearby' => $nearbyCount,
            'textSearch' => $textSearchCount,
            'merged' => count($restaurants),
        ];

        return $restaurants;
    }

    /**
     * Restaurant name/food/category/cuisine/dish search, scoped to the caller's current Nearby
     * browse center. MakanApa's own DB is searched first and always included; Google Text Search
     * is only called as a supplemental fallback when local results are thin — not on every
     * keystroke — so the common case (our own data already covers the query) never pays for a
     * Google call at all.
     *
     * Ranking is match-quality first (exact/prefix/contains name, then dish, then menu item, then
     * category/cuisine, then distance), with provenance only breaking ties — a strong Google match
     * must never lose to a weak community one just because community rows are "ours."
     *
     * @return array<int, array<string, mixed>> each tagged 'provenance': canonical|community|google_fallback
     */
    public function searchPlaces(string $query, float $latitude, float $longitude): array
    {
        $local = $this->searchLocalRestaurants($query, $latitude, $longitude);

        $results = $local;
        // Same PLACES_PROVIDER gate nearbyRestaurants() uses — fixture-mode environments (tests,
        // local dev without a Google key configured) must never place a live API call just
        // because search happens to also read GOOGLE_PLACES_API_KEY from the environment.
        $usesGoogle = Config::get('services.places.provider', 'fixture') === 'google';
        if ($usesGoogle && count($local) < self::GOOGLE_FALLBACK_MIN_LOCAL_RESULTS) {
            $apiKey = Config::get('services.places.google_api_key');
            if (! empty($apiKey)) {
                try {
                    $results = array_merge($results, $this->searchGoogleFallback($query, $latitude, $longitude, $apiKey));
                } catch (Throwable $e) {
                    Log::warning('Places search: Google text search fallback failed, returning local results only', [
                        'error' => $e->getMessage(),
                        'query' => $query,
                    ]);
                }
            }
        }

        return $this->rankSearchResults($results, $query);
    }

    /**
     * Turns a google_fallback search result (no restaurant_id yet) into a canonical `restaurants`
     * row, the one path every search result eventually converges on before its detail sheet opens
     * — Google is a provider, `restaurants` is MakanApa's canonical restaurant graph.
     */
    public function resolveGooglePlace(string $googlePlaceId): array
    {
        if (Config::get('services.places.provider', 'fixture') !== 'google') {
            throw new RuntimeException('PLACES_PROVIDER=google requires GOOGLE_PLACES_API_KEY to be set.');
        }

        $apiKey = Config::get('services.places.google_api_key');
        if (empty($apiKey)) {
            throw new RuntimeException('PLACES_PROVIDER=google requires GOOGLE_PLACES_API_KEY to be set.');
        }

        $existing = Restaurant::where('provider', 'google')->where('provider_place_id', $googlePlaceId)->first();
        if ($existing) {
            $existing->load(['cuisines', 'tags']);

            return $existing->toSearchResultArray('canonical');
        }

        $place = (new GooglePlacesProvider($apiKey))->fetchPlace($googlePlaceId);
        $data = $this->normalizer->normalize(collect([$place]))->first();
        $restaurant = $this->upsertRestaurant($data);
        $restaurant->load(['cuisines', 'tags']);

        return $restaurant->toSearchResultArray('canonical');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchLocalRestaurants(string $query, float $latitude, float $longitude): array
    {
        $pattern = '%'.$this->escapeLike($query).'%';

        return Restaurant::query()
            ->where('is_active', true)
            ->where(function ($q) use ($pattern) {
                $q->where('name', 'ilike', $pattern)
                    ->orWhere('food_category', 'ilike', $pattern)
                    ->orWhere('signature_dish', 'ilike', $pattern)
                    // whereHas()/EXISTS, not a join — a restaurant with several matching cuisines
                    // or menu items must still count once, not fan out into duplicate rows.
                    ->orWhereHas('cuisines', fn ($c) => $c->where('slug', 'ilike', $pattern)->orWhere('name', 'ilike', $pattern))
                    ->orWhereHas('menuItems', fn ($m) => $m->where('name', 'ilike', $pattern));
            })
            ->with(['cuisines', 'tags', 'menuItems'])
            ->get()
            ->map(fn (Restaurant $r) => [$r, RecommendationService::distanceKm($latitude, $longitude, (float) $r->latitude, (float) $r->longitude)])
            ->filter(fn (array $pair) => $pair[1] <= self::SEARCH_RADIUS_KM)
            ->map(fn (array $pair) => [
                ...$pair[0]->toSearchResultArray(
                    $pair[0]->provider === 'user_submitted' ? 'community' : 'canonical',
                    $pair[1]
                ),
                // Ranking-only signal (matchTier()) — not part of the restaurant's general shape,
                // so it lives here rather than on toSearchResultArray() itself.
                'menu_item_names' => $pair[0]->menuItems->pluck('name')->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchGoogleFallback(string $query, float $latitude, float $longitude, string $apiKey): array
    {
        $existingPlaceIds = Restaurant::where('provider', 'google')->pluck('provider_place_id')->all();

        $places = (new GooglePlacesProvider($apiKey))->searchText($query, $latitude, $longitude, self::SEARCH_RADIUS_KM, null);

        return $places
            ->reject(fn (ProviderPlace $place) => in_array($place->providerPlaceId, $existingPlaceIds, true))
            ->map(fn (ProviderPlace $place) => [
                'id' => null,
                'google_place_id' => $place->providerPlaceId,
                'name' => $place->name,
                'latitude' => $place->latitude,
                'longitude' => $place->longitude,
                'price_level' => $place->priceLevel,
                'rating' => $place->rating,
                'food_category' => null,
                'signature_dish' => null,
                'cuisines' => [],
                'tags' => [],
                'provenance' => 'google_fallback',
                'is_community_find' => false,
                'distance_km' => RecommendationService::distanceKm($latitude, $longitude, $place->latitude, $place->longitude),
            ])
            ->values()
            ->all();
    }

    /**
     * Match-quality tier, ascending = better: exact name, name prefix, name contains, signature
     * dish, menu item, category/cuisine, no direct text match (reachable when a row only matched
     * via a join clause the tiering below doesn't independently re-check). Deliberately ignores
     * provenance — a canonical/Google exact match must always beat a community category-only
     * match, never the other way around just because community rows are "ours."
     */
    private function matchTier(array $result, string $normalizedQuery): int
    {
        $name = mb_strtolower((string) ($result['name'] ?? ''));

        if ($name === $normalizedQuery) {
            return 0;
        }
        if (str_starts_with($name, $normalizedQuery)) {
            return 1;
        }
        if (str_contains($name, $normalizedQuery)) {
            return 2;
        }
        if (str_contains(mb_strtolower((string) ($result['signature_dish'] ?? '')), $normalizedQuery)) {
            return 3;
        }
        if (collect($result['menu_item_names'] ?? [])->contains(fn ($n) => str_contains(mb_strtolower($n), $normalizedQuery))) {
            return 4;
        }
        $categoryish = mb_strtolower((string) ($result['food_category'] ?? ''));
        $cuisineHit = collect($result['cuisines'] ?? [])->contains(fn ($c) => str_contains(mb_strtolower($c), $normalizedQuery));
        if (str_contains($categoryish, $normalizedQuery) || $cuisineHit) {
            return 5;
        }

        return 6;
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     * @return array<int, array<string, mixed>>
     */
    private function rankSearchResults(array $results, string $query): array
    {
        $normalizedQuery = mb_strtolower(trim($query));

        $ranked = collect($results)
            ->map(function (array $result) use ($normalizedQuery) {
                $result['_tier'] = $this->matchTier($result, $normalizedQuery);
                $result['_provenanceBoost'] = $result['provenance'] === 'google_fallback' ? 1 : 0;

                return $result;
            })
            ->sortBy([
                ['_tier', 'asc'],
                ['_provenanceBoost', 'asc'],
                ['distance_km', 'asc'],
            ])
            ->values()
            ->map(fn (array $result) => Arr::except($result, ['_tier', '_provenanceBoost', 'menu_item_names']))
            ->all();

        return $ranked;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($value));
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

    /**
     * Below the threshold: a single tile identical to the original (non-tiled) circle — the
     * common case (map browsing, "5 min"/"10 min" Decide radii) pays no extra cost. Above it:
     * one center circle plus a 6-circle hex ring, each at half the original radius — a standard
     * circle-covering pattern, not pixel-perfect coverage, but enough to pull real results out
     * from under the 20-per-call cap. Always exactly MAX_TILES (7) when triggered, never a
     * variable count, so cost per request is predictable.
     *
     * @return array<int, array{lat: float, lon: float, radius: float}>
     */
    private function tileCircles(float $latitude, float $longitude, float $radiusKm): array
    {
        if ($radiusKm <= self::TILE_RADIUS_THRESHOLD_KM) {
            return [['lat' => $latitude, 'lon' => $longitude, 'radius' => $radiusKm]];
        }

        $subRadius = $radiusKm / 2;
        $ringDistance = $subRadius * sqrt(3);

        $tiles = [['lat' => $latitude, 'lon' => $longitude, 'radius' => $subRadius]];
        for ($i = 0; $i < self::MAX_TILES - 1; $i++) {
            $bearing = $i * (360 / (self::MAX_TILES - 1));
            [$tileLat, $tileLon] = self::offsetCoordinate($latitude, $longitude, $ringDistance, $bearing);
            $tiles[] = ['lat' => $tileLat, 'lon' => $tileLon, 'radius' => $subRadius];
        }

        return $tiles;
    }

    /** Great-circle destination point — standard forward geodesic formula, not a flat-earth approximation. */
    private static function offsetCoordinate(float $latitude, float $longitude, float $distanceKm, float $bearingDegrees): array
    {
        $earthRadiusKm = 6371.0;
        $bearing = deg2rad($bearingDegrees);
        $lat1 = deg2rad($latitude);
        $lon1 = deg2rad($longitude);
        $angularDistance = $distanceKm / $earthRadiusKm;

        $lat2 = asin(sin($lat1) * cos($angularDistance) + cos($lat1) * sin($angularDistance) * cos($bearing));
        $lon2 = $lon1 + atan2(
            sin($bearing) * sin($angularDistance) * cos($lat1),
            cos($angularDistance) - sin($lat1) * sin($lat2)
        );

        return [rad2deg($lat2), rad2deg($lon2)];
    }

    /** @return string[] */
    private function includedTypesFor(?DiscoveryMode $mode): array
    {
        return match ($mode) {
            DiscoveryMode::Cafe, DiscoveryMode::LowKey => [...self::BASE_TYPES, ...self::CAFE_EXTRA_TYPES],
            default => self::BASE_TYPES,
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
                // e.g. a Cafe-mode sync covers a later Normal request, but not vice versa. The
                // 1-meter tolerance absorbs float/decimal(10,7) rounding noise between a freshly
                // computed tile center (tileCircles()) and the same center round-tripped through
                // DB storage — without it, an identical repeat request can miss its own cache by
                // a fraction of a millimeter and needlessly re-sync.
                $locationCovered = $distanceToCenter + $radiusKm <= (float) $area->radius_km + 0.001;
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

    /**
     * Respects `restaurant_field_overrides`: an admin-verified correction on an existing
     * restaurant is excluded from this update payload entirely — Google's fresh data for that
     * field is simply discarded for this sync, not just ignored at read time. New rows (first
     * sync for this provider_place_id) have no overrides yet, so this is a no-op for genuinely
     * new places.
     */
    private function upsertRestaurant(array $data): Restaurant
    {
        $existing = Restaurant::where('provider', 'google')->where('provider_place_id', $data['provider_place_id'])->first();

        $payload = [
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
        ];

        if ($existing) {
            $overriddenFields = RestaurantFieldOverride::where('restaurant_id', $existing->id)->pluck('field')->all();
            $payload = Arr::except($payload, $overriddenFields);
        }

        $restaurant = Restaurant::updateOrCreate(
            ['provider' => 'google', 'provider_place_id' => $data['provider_place_id']],
            $payload
        );

        $cuisineIds = collect($data['cuisines'])->map(
            fn (string $slug) => Cuisine::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)])->id
        );
        $restaurant->cuisines()->sync($cuisineIds);

        $tagIds = collect($data['tags'])->map(
            fn (string $name) => Tag::firstOrCreate(['name' => $name])->id
        );
        $restaurant->tags()->sync($tagIds);

        return $restaurant;
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
     * Instead this only excludes what the leak bug actually was: a place typed as one of
     * CAFE_EXTRA_TYPES (cafe/coffee_shop/bakery) showing up in a mode that never asked for those
     * types. A row with no recorded google_types (synced before that column existed) is always
     * treated as compatible.
     */
    private function readGoogleRestaurantsNear(float $latitude, float $longitude, float $radiusKm, array $includedTypes): array
    {
        $modeAllowsCafeLeaning = ! empty(array_intersect($includedTypes, self::CAFE_EXTRA_TYPES));

        return Restaurant::where('provider', 'google')
            ->where('is_active', true)
            ->with(['cuisines', 'tags'])
            ->get()
            ->filter(fn (Restaurant $restaurant) => RecommendationService::distanceKm(
                $latitude, $longitude, (float) $restaurant->latitude, (float) $restaurant->longitude
            ) <= $radiusKm)
            ->filter(fn (Restaurant $restaurant) => $modeAllowsCafeLeaning
                || empty($restaurant->google_types)
                || empty(array_intersect($restaurant->google_types, self::CAFE_EXTRA_TYPES)))
            ->map(fn (Restaurant $restaurant) => $restaurant->toRecommendationArray())
            ->values()
            ->all();
    }

    /**
     * Community-submitted restaurants (admin-approved only — pending/rejected submissions never
     * reach `restaurants` at all, see Admin\RestaurantSubmissionController). No Google-types
     * cafe-leaning filter here — that's Google-type-specific and meaningless for these rows.
     */
    private function readUserSubmittedRestaurantsNear(float $latitude, float $longitude, float $radiusKm): array
    {
        return Restaurant::where('provider', 'user_submitted')
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
