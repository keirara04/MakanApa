<?php

namespace App\Services;

use App\Jobs\SecondOpinionNonHalal;
use App\Models\Cuisine;
use App\Models\PlaceSyncArea;
use App\Models\Restaurant;
use App\Models\RestaurantFieldOverride;
use App\Models\Tag;
use App\Services\Craving\CravingIntent;
use App\Services\Halal\HalalVerificationService;
use App\Services\Judgment\Definitions\NonHalalSecondOpinionGate;
use App\Services\Places\FixturePlacesProvider;
use App\Services\Places\GooglePlacesProvider;
use App\Services\Places\PlaceNormalizer;
use App\Services\Places\ProviderPlace;
use App\Support\DiscoveryMode;
use App\Support\FoodTaxonomy;
use App\Support\Halal\HalalEligibility;
use App\Support\Halal\HalalHeuristic;
use App\Support\Halal\HalalStatus;
use App\Support\OpeningHours;
use App\Support\Vibe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
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

    /**
     * Above this radius, a single Nearby Search's 20-result cap starts leaving real coverage
     * gaps — see syncFromGoogle()'s tiling. Lowered from 2.5km: in denser areas (e.g. around a
     * university) 20 results was already hit well under 2.5km, silently capping the map at ~20
     * places even at a normal browsing zoom. Safe to lower — tiling is gated per-tile by
     * isAreaCovered()'s 24h cache, so this only adds one-time Google calls per newly-seen tile,
     * not a repeat cost on every request.
     */
    private const TILE_RADIUS_THRESHOLD_KM = 1.0;

    /** Hard cap on tiles per request, regardless of how large radiusKm is — bounds Google API cost from one client request. */
    private const MAX_TILES = 7;

    /** searchPlaces()'s radius around the caller's Nearby browse center — a fixed, generous "somewhere in the area" scope, not the viewport-derived radius nearbyRestaurants() uses. */
    private const SEARCH_RADIUS_KM = 6.0;

    /** "Search wider" steps for searchPlaces() — the client asks for the next rung via widerRadiusKm. */
    private const SEARCH_RADIUS_LADDER_KM = [6.0, 12.0, 25.0];

    /**
     * Google is consulted automatically only while local results hold fewer than this many
     * *strong* (name) matches — three fuzzy category/menu hits must not block Google, but three
     * real "KFC"s mean our own data already answers the question. Otherwise the client offers an
     * explicit "Search Google" row (includeGoogle=true), so cost stays under the user's control.
     */
    private const GOOGLE_FALLBACK_MIN_STRONG_MATCHES = 3;

    /** matchTier() at or below this is a name match — what counts as "strong" for the rule above. */
    private const STRONG_MATCH_MAX_TIER = 2;

    /** "kf" never pays for a Google call on its own; the local search still runs. */
    private const GOOGLE_FALLBACK_MIN_QUERY_LENGTH = 3;

    /** Did-you-mean suggestions only ever come from these many nearby names/aliases. */
    private const SUGGESTION_LIMIT = 3;

    /** How far outside the request a synced circle's center may sit and still be considered for coverage — comfortably above any tile/viewport radius. */
    private const SYNC_AREA_SEARCH_MARGIN_KM = 10.0;

    /** Points per ring when checking whether the union of synced circles covers a request (see isAreaCovered()). */
    private const COVERAGE_SAMPLE_BEARINGS = 16;

    /** @var array<string, int> cuisine slug => id, memoized across upserts in one sync */
    private array $cuisineIds = [];

    /** @var array<string, int> tag name => id, memoized across upserts in one sync */
    private array $tagIds = [];

    public function __construct(
        private readonly PlaceNormalizer $normalizer,
        private readonly HalalVerificationService $halalVerifications,
    ) {}

    /** Set by nearbyRestaurants() when a craving is present, for RecommendationController's debug payload only. */
    private array $lastCandidateCounts = [];

    /**
     * Set by searchPlaces() — how the last search was answered, for PlaceSearchController's `meta`.
     *
     * @var array{radius_km: float, source: string, google_available: bool, wider_radius_km: ?float, total: int, suggestions: string[]}|array{}
     */
    private array $lastSearchMeta = [];

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
        $tiles = $this->tileCircles($latitude, $longitude, $radiusKm);
        // One query for every tile's coverage check, not one full-table read per tile.
        $freshAreas = $this->freshSyncAreasNear($latitude, $longitude, $radiusKm, $includedTypes);
        $uncoveredTiles = array_values(array_filter(
            $tiles,
            fn (array $tile) => ! $this->isAreaCovered($tile['lat'], $tile['lon'], $tile['radius'], $freshAreas)
        ));

        if (! empty($uncoveredTiles)) {
            $this->syncTilesFromGoogle($uncoveredTiles, $apiKey, $includedTypes);
        }

        $nearbyCount = null;
        $textSearchLanes = [];
        if ($craving !== null && $craving->primarySearchTerm() !== null) {
            // Debug payload only — a second full read of the area isn't worth paying otherwise.
            if (Config::get('recommendation.debug')) {
                $nearbyCount = count($this->readGoogleRestaurantsNear($latitude, $longitude, $radiusKm, $includedTypes));
            }

            $cravingDiscriminator = $craving->concept
                ?? strtolower(trim(preg_replace('/\s+/', ' ', $craving->raw) ?? ''));
            // The concept's specific Google type when FoodTaxonomy has one (ice cream ->
            // ice_cream_shop, dessert -> dessert_restaurant) — narrower than 'restaurant'
            // without reopening the "craving search goes unrestricted" bug this replaces.
            // Falls back to 'restaurant' for dish concepts with no placeTypes entry and for
            // unresolved/raw-text cravings, i.e. today's exact prior behavior.
            $textSearchLanes[] = [
                'kind' => 'craving',
                'query' => $craving->primarySearchTerm(),
                'discriminator' => 'craving:'.$cravingDiscriminator,
                'includedType' => $craving->placeTypes[0] ?? 'restaurant',
            ];
        }

        // Unrestricted (no includedType) — Google's Text Search only accepts one type, and a
        // lane meant to catch cafe-or-coffee_shop-or-bakery can't be expressed as a single
        // restriction anyway, so narrowing it here would just silently drop valid matches.
        foreach ($this->discoveryTextQueries($mode, $vibe) as $discoveryQuery) {
            $textSearchLanes[] = [
                'kind' => 'discovery',
                'query' => $discoveryQuery,
                'discriminator' => 'discovery:'.$discoveryQuery,
                'includedType' => null,
            ];
        }

        $textSearchCount = $this->syncTextSearchLanes($latitude, $longitude, $radiusKm, $apiKey, $textSearchLanes);

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
     * Restaurant name/food/category/cuisine/dish search, scoped to a radius around the caller's
     * Nearby browse center. MakanApa's own DB is searched first and always included; Google Text
     * Search is a supplemental fallback — automatic only while local strong matches are thin
     * (see GOOGLE_FALLBACK_MIN_STRONG_MATCHES), otherwise on explicit request ($includeGoogle).
     *
     * Ranking answers "which place did you mean", not "where should you eat": match quality
     * first, then provenance, then distance (1 km buckets), open before closed, rating, and exact
     * distance as the final deterministic tiebreak. Likely branches of one chain are then pulled
     * together (see groupBranches()) without hiding any of them.
     *
     * @return array<int, array<string, mixed>> each tagged 'provenance': canonical|community|google_fallback
     */
    public function searchPlaces(
        string $query, float $latitude, float $longitude,
        float $radiusKm = self::SEARCH_RADIUS_KM, bool $halalOnly = false, ?bool $includeGoogle = null,
    ): array {
        $normalizedQuery = mb_strtolower(trim($query));
        $local = HalalEligibility::filter($this->searchLocalRestaurants($query, $latitude, $longitude, $radiusKm), $halalOnly);

        $strongLocalMatches = count(array_filter(
            $local, fn (array $result) => $this->matchTier($result, $normalizedQuery) <= self::STRONG_MATCH_MAX_TIER
        ));

        // Same PLACES_PROVIDER gate nearbyRestaurants() uses — fixture-mode environments (tests,
        // local dev without a Google key configured) must never place a live API call just
        // because search happens to also read GOOGLE_PLACES_API_KEY from the environment.
        $apiKey = Config::get('services.places.google_api_key');
        $googleConfigured = Config::get('services.places.provider', 'fixture') === 'google' && ! empty($apiKey);
        $wantsGoogle = $includeGoogle ?? (
            $strongLocalMatches < self::GOOGLE_FALLBACK_MIN_STRONG_MATCHES
            && mb_strlen($normalizedQuery) >= self::GOOGLE_FALLBACK_MIN_QUERY_LENGTH
        );

        $results = $local;
        $askedGoogle = false;
        if ($googleConfigured && $wantsGoogle) {
            try {
                $results = array_merge($results, $this->searchGoogleFallback($query, $latitude, $longitude, $radiusKm, $apiKey));
                $askedGoogle = true;
            } catch (Throwable $e) {
                Log::warning('Places search: Google text search fallback failed, returning local results only', [
                    'error' => $e->getMessage(),
                    'query' => $query,
                ]);
            }
        }

        $ranked = $this->groupBranches($this->rankSearchResults($results, $query));

        $provenances = array_unique(array_column($ranked, 'provenance'));
        $hasGoogle = in_array('google_fallback', $provenances, true);
        $this->lastSearchMeta = [
            'radius_km' => $radiusKm,
            'source' => match (true) {
                $hasGoogle && count($provenances) > 1 => 'mixed',
                $hasGoogle => 'google',
                default => 'local',
            },
            'google_available' => $googleConfigured && ! $askedGoogle,
            'wider_radius_km' => collect(self::SEARCH_RADIUS_LADDER_KM)->first(fn (float $rung) => $rung > $radiusKm),
            'total' => count($ranked),
            'suggestions' => $ranked === [] ? $this->searchSuggestions($normalizedQuery, $latitude, $longitude, $radiusKm) : [],
        ];

        return $ranked;
    }

    /** @return array{radius_km: float, source: string, google_available: bool, wider_radius_km: ?float, total: int, suggestions: string[]}|array{} */
    public function lastSearchMeta(): array
    {
        return $this->lastSearchMeta;
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

        $existing = Restaurant::where('provider', 'google')->where('provider_place_id', $googlePlaceId)->first()?->canonicalRestaurant();
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
    private function searchLocalRestaurants(string $query, float $latitude, float $longitude, float $radiusKm): array
    {
        $pattern = '%'.$this->escapeLike($query).'%';
        [$latDelta, $lngDelta] = self::boundingBoxDeltas($latitude, $radiusKm);

        return Restaurant::query()
            ->where('is_active', true)
            // Box first so the text match only runs over the search area, not the whole table;
            // the exact Haversine cutoff below still trims the corners.
            ->whereBetween('latitude', [$latitude - $latDelta, $latitude + $latDelta])
            ->whereBetween('longitude', [$longitude - $lngDelta, $longitude + $lngDelta])
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
            ->filter(fn (array $pair) => $pair[1] <= $radiusKm)
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
    private function searchGoogleFallback(string $query, float $latitude, float $longitude, float $radiusKm, string $apiKey): array
    {
        $places = (new GooglePlacesProvider($apiKey))->searchText($query, $latitude, $longitude, $radiusKm, null);

        // Only the IDs Google just returned — never the whole restaurants table.
        $existingPlaceIds = Restaurant::where('provider', 'google')
            ->whereIn('provider_place_id', $places->map(fn (ProviderPlace $place) => $place->providerPlaceId)->all())
            ->pluck('provider_place_id')
            ->flip();

        return $this->normalizer->normalize($places->reject(fn (ProviderPlace $place) => $existingPlaceIds->has($place->providerPlaceId))->values())
            ->map(fn (array $data) => [
                'id' => null,
                'google_place_id' => $data['provider_place_id'],
                'name' => $data['name'],
                'address' => $data['address'],
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'price_level' => $data['price_level'],
                'rating' => $data['rating'],
                'food_category' => $data['food_category'],
                'signature_dish' => null,
                'cuisines' => $data['cuisines'],
                'tags' => [],
                'open_status' => OpeningHours::status($data['opening_hours'], now()),
                'closes_at' => OpeningHours::closesAt($data['opening_hours'], now()),
                'halal_status' => HalalStatus::Unknown->value,
                'provenance' => 'google_fallback',
                'is_community_find' => false,
                'distance_km' => RecommendationService::distanceKm($latitude, $longitude, $data['latitude'], $data['longitude']),
            ])
            // Text Search's location is only a bias — keep the same radius promise local results make.
            ->filter(fn (array $result) => $result['distance_km'] <= $radiusKm)
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

        $scored = collect($results)->map(function (array $result) use ($normalizedQuery) {
            $result['_tier'] = $this->matchTier($result, $normalizedQuery);

            return $result;
        });

        // A row that only matched through a join the tiering can't re-check is noise once any
        // real match exists.
        $bestTier = $scored->min('_tier');
        if ($bestTier !== null && $bestTier < 6) {
            $scored = $scored->reject(fn (array $result) => $result['_tier'] === 6);
        }

        return $scored
            ->sort(function (array $a, array $b) {
                return [
                    $a['_tier'],
                    $a['provenance'] === 'google_fallback' ? 1 : 0,
                    (int) floor($a['distance_km'] ?? 999),
                    ($a['open_status'] ?? 'unknown') === 'closed' ? 1 : 0,
                    -($a['rating'] ?? 0),
                    $a['distance_km'] ?? 999,
                    $a['name'],
                ] <=> [
                    $b['_tier'],
                    $b['provenance'] === 'google_fallback' ? 1 : 0,
                    (int) floor($b['distance_km'] ?? 999),
                    ($b['open_status'] ?? 'unknown') === 'closed' ? 1 : 0,
                    -($b['rating'] ?? 0),
                    $b['distance_km'] ?? 999,
                    $b['name'],
                ];
            })
            ->values()
            ->map(fn (array $result) => Arr::except($result, ['_tier', 'menu_item_names']))
            ->all();
    }

    /**
     * Likely branches of one chain ("KFC" ×4) are pulled together at the position of the best-
     * ranked one and tagged with a shared group_key/group_size, so the client can show a
     * "KFC · 4 nearby" cue — every branch stays visible, nothing collapses. A shared name alone
     * isn't enough (two unrelated "Restoran Ali" shops): the rows must also share a non-null
     * food_category or website host.
     *
     * @param  array<int, array<string, mixed>>  $ranked
     * @return array<int, array<string, mixed>>
     */
    private function groupBranches(array $ranked): array
    {
        $groups = [];
        foreach ($ranked as $index => $result) {
            $name = self::normalizedName($result['name'] ?? '');
            foreach (array_filter([
                'category:'.($result['food_category'] ?? ''),
                'web:'.(parse_url((string) ($result['website_url'] ?? ''), PHP_URL_HOST) ?: ''),
            ], fn (string $signal) => ! str_ends_with($signal, ':')) as $signal) {
                $groups["{$name}|{$signal}"][] = $index;
            }
        }

        $groupOf = [];
        foreach ($groups as $key => $members) {
            if (count($members) < 2) {
                continue;
            }
            foreach ($members as $index) {
                // First qualifying group wins; a row never belongs to two groups.
                $groupOf[$index] ??= ['key' => $key, 'members' => $members];
            }
        }

        $ordered = [];
        $emitted = [];
        foreach ($ranked as $index => $result) {
            if (isset($emitted[$index])) {
                continue;
            }
            $group = $groupOf[$index] ?? null;
            $members = $group ? array_values(array_filter($group['members'], fn (int $m) => ($groupOf[$m]['key'] ?? null) === $group['key'])) : [$index];
            foreach ($members as $member) {
                $emitted[$member] = true;
                $ordered[] = [
                    ...$ranked[$member],
                    'group_key' => $group ? self::normalizedName($ranked[$member]['name'] ?? '') : null,
                    'group_size' => $group ? count($members) : 1,
                ];
            }
        }

        return $ordered;
    }

    private static function normalizedName(string $name): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($name)));
    }

    /**
     * "Did you mean" for an empty result set — deterministic, no LLM: the food concept the query
     * resolves to (its label/search terms), plus nearby restaurant names and taxonomy aliases
     * within a small edit distance of the query (typos like "kfcc", "mcdonlds").
     *
     * @return string[]
     */
    private function searchSuggestions(string $normalizedQuery, float $latitude, float $longitude, float $radiusKm): array
    {
        if (mb_strlen($normalizedQuery) < 3) {
            return [];
        }

        $suggestions = [];
        if ($concept = FoodTaxonomy::resolve($normalizedQuery)) {
            $suggestions[] = FoodTaxonomy::label($concept['concept']);
            array_push($suggestions, ...$concept['searchTerms']);
        }

        [$latDelta, $lngDelta] = self::boundingBoxDeltas($latitude, $radiusKm);
        $nearbyNames = Restaurant::query()
            ->where('is_active', true)
            ->whereBetween('latitude', [$latitude - $latDelta, $latitude + $latDelta])
            ->whereBetween('longitude', [$longitude - $lngDelta, $longitude + $lngDelta])
            ->distinct()
            ->limit(500)
            ->pluck('name')
            ->all();
        $aliases = collect(FoodTaxonomy::CONCEPTS)->pluck('aliases')->flatten()->all();

        $maxDistance = max(1, intdiv(mb_strlen($normalizedQuery), 4));
        $close = collect([...$nearbyNames, ...$aliases])
            ->filter(fn (string $candidate) => mb_strlen($candidate) <= 40)
            ->map(fn (string $candidate) => [$candidate, levenshtein($normalizedQuery, mb_strtolower($candidate))])
            ->filter(fn (array $pair) => $pair[1] > 0 && $pair[1] <= $maxDistance)
            ->sortBy(fn (array $pair) => [$pair[1], mb_strlen($pair[0])])
            ->map(fn (array $pair) => $pair[0])
            ->all();
        array_push($suggestions, ...$close);

        return collect($suggestions)
            ->filter()
            ->unique(fn (string $suggestion) => mb_strtolower($suggestion))
            ->reject(fn (string $suggestion) => mb_strtolower($suggestion) === $normalizedQuery)
            ->take(self::SUGGESTION_LIMIT)
            ->values()
            ->all();
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
     * Every fresh synced area that could overlap this request and already fetched every type it
     * needs (a superset — a Cafe-mode sync covers a later Normal request, but not vice versa).
     * Loaded once per request; isAreaCovered() then runs per tile in memory.
     *
     * @param  string[]  $requiredTypes
     * @return Collection<int, PlaceSyncArea>
     */
    private function freshSyncAreasNear(float $latitude, float $longitude, float $radiusKm, array $requiredTypes): Collection
    {
        $cacheHours = (int) Config::get('services.places.cache_hours', 24);
        // Generous margin: a synced circle centred outside the request can still reach into it.
        [$latDelta, $lngDelta] = self::boundingBoxDeltas($latitude, $radiusKm + self::SYNC_AREA_SEARCH_MARGIN_KM);

        return PlaceSyncArea::where('provider', 'google')
            ->where('synced_at', '>=', now()->subHours($cacheHours))
            ->whereBetween('latitude', [$latitude - $latDelta, $latitude + $latDelta])
            ->whereBetween('longitude', [$longitude - $lngDelta, $longitude + $lngDelta])
            ->get(['latitude', 'longitude', 'radius_km', 'types'])
            ->filter(fn (PlaceSyncArea $area) => empty(array_diff($requiredTypes, $area->types ?? [])))
            ->values();
    }

    /**
     * Covered when the requested circle sits fully inside one already-synced circle, or — so a
     * small pan doesn't re-bill Google for data already stored — when the union of synced circles
     * covers it: its center, a ring at half radius and its whole edge all land inside some synced
     * circle. The 1-meter tolerance absorbs float/decimal(10,7) rounding noise between a freshly
     * computed tile center (tileCircles()) and the same center round-tripped through DB storage.
     *
     * @param  Collection<int, PlaceSyncArea>  $freshAreas  from freshSyncAreasNear()
     */
    private function isAreaCovered(float $latitude, float $longitude, float $radiusKm, Collection $freshAreas): bool
    {
        if ($freshAreas->isEmpty()) {
            return false;
        }

        $insideAnArea = fn (float $lat, float $lon, float $radius) => $freshAreas->contains(
            fn (PlaceSyncArea $area) => RecommendationService::distanceKm(
                (float) $area->latitude, (float) $area->longitude, $lat, $lon
            ) + $radius <= (float) $area->radius_km + 0.001
        );

        if ($insideAnArea($latitude, $longitude, $radiusKm)) {
            return true;
        }

        $samplePoints = [[$latitude, $longitude]];
        foreach ([$radiusKm, $radiusKm / 2] as $ringRadius) {
            for ($i = 0; $i < self::COVERAGE_SAMPLE_BEARINGS; $i++) {
                $samplePoints[] = self::offsetCoordinate($latitude, $longitude, $ringRadius, $i * (360 / self::COVERAGE_SAMPLE_BEARINGS));
            }
        }

        foreach ($samplePoints as [$pointLat, $pointLon]) {
            if (! $insideAnArea($pointLat, $pointLon, 0.0)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Fetches every uncovered tile concurrently (see GooglePlacesProvider::nearbyRestaurantsBatch)
     * instead of one Nearby Search per tile in sequence, then upserts/marks each tile covered in
     * request order for deterministic PlaceSyncArea rows regardless of which HTTP response lands
     * first.
     *
     * @param  array<int, array{lat: float, lon: float, radius: float}>  $tiles
     * @param  string[]  $includedTypes
     */
    private function syncTilesFromGoogle(array $tiles, string $apiKey, array $includedTypes): void
    {
        $providerPlacesByTile = (new GooglePlacesProvider($apiKey))->nearbyRestaurantsBatch($tiles, $includedTypes);

        foreach ($tiles as $i => $tile) {
            $normalized = $this->normalizer->normalize($providerPlacesByTile[$i]);

            foreach ($normalized as $data) {
                $this->upsertRestaurant($data);
            }

            PlaceSyncArea::create([
                'provider' => 'google',
                'latitude' => $tile['lat'],
                'longitude' => $tile['lon'],
                'radius_km' => $tile['radius'],
                'synced_at' => now(),
                'types' => $includedTypes,
            ]);
        }
    }

    /**
     * One Google Text Search call per distinct lane (craving + discovery/vibe lanes), all cache
     * misses fired concurrently. The result set itself is cached so a cluster of nearby users
     * requesting the same thing doesn't multiply Google billing. Results are upserted only on a
     * cache miss: a hit means that exact result set was already written within the cache window,
     * and re-upserting ~20 places cost hundreds of queries per request for no new data.
     *
     * Text Search is an opportunistic boost, never a required part of the response — a failing
     * lane (Google rate limit, transient 5xx, bad query) is logged and skipped, never thrown.
     *
     * @param  array<int, array{kind: string, query: string, discriminator: string, includedType: ?string}>  $lanes
     * @return int number of places the lanes returned (cache hit or miss)
     */
    private function syncTextSearchLanes(float $latitude, float $longitude, float $radiusKm, string $apiKey, array $lanes): int
    {
        $count = 0;
        $misses = [];

        foreach ($lanes as $lane) {
            // includedType is part of the cache key — the same query text restricted to
            // 'restaurant' vs unrestricted are different requests, must not share a cache entry.
            $key = $this->textSearchCacheKey($latitude, $longitude, $radiusKm, $lane['discriminator'].':'.($lane['includedType'] ?? 'any'));
            $cached = Cache::get($key);
            if ($cached !== null) {
                $count += count($cached);

                continue;
            }
            $misses[] = [...$lane, 'cacheKey' => $key];
        }

        if (empty($misses)) {
            return $count;
        }

        $results = (new GooglePlacesProvider($apiKey))->searchTextBatch(
            array_map(fn (array $lane) => ['query' => $lane['query'], 'includedType' => $lane['includedType']], $misses),
            $latitude, $longitude, $radiusKm,
        );

        foreach ($misses as $index => $lane) {
            $result = $results[$index];
            if ($result instanceof Throwable) {
                Log::warning($lane['kind'] === 'craving'
                    ? 'Craving text search failed, continuing with nearby-only results'
                    : 'Discovery text search failed, continuing without it', [
                        'error' => $result->getMessage(),
                        'query' => $lane['query'],
                    ]);

                continue;
            }

            $normalized = $this->normalizer->normalize($result)->all();
            Cache::put($lane['cacheKey'], $normalized, now()->addMinutes(60));
            foreach ($normalized as $data) {
                $this->upsertRestaurant($data);
            }
            $count += count($normalized);
        }

        return $count;
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
     *
     * If this provider_place_id belongs to a restaurant that's been merged away by
     * RestaurantMergeService (merged_into_restaurant_id set), the sync must redirect onto the
     * canonical restaurant instead — never write to (and so never reactivate) the merged-away
     * row. provider_place_id is deliberately left on the merged-away row, so the lookup below
     * still finds it every sync; canonicalRestaurant() is what keeps the write off of it.
     */
    private function upsertRestaurant(array $data): Restaurant
    {
        $found = Restaurant::where('provider', 'google')->where('provider_place_id', $data['provider_place_id'])->first();
        $existing = $found?->canonicalRestaurant();

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
        // Only when Google sent one — a sync without the field must not blank an address we have.
        if (! empty($data['address'])) {
            $payload['address'] = $data['address'];
        }

        if ($existing) {
            $overriddenFields = RestaurantFieldOverride::where('restaurant_id', $existing->id)->pluck('field')->all();
            $payload = Arr::except($payload, $overriddenFields);

            $existing->update($payload);
            $restaurant = $existing;
        } else {
            $restaurant = Restaurant::create([
                'provider' => 'google',
                'provider_place_id' => $data['provider_place_id'],
                ...$payload,
            ]);
        }

        // Memoized per service instance — a 7-tile sync upserts up to 140 places that share a
        // handful of cuisines/tags, which used to be one firstOrCreate() query per place each.
        $cuisineIds = collect($data['cuisines'])->map(
            fn (string $slug) => $this->cuisineIds[$slug] ??= Cuisine::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)])->id
        );
        $restaurant->cuisines()->sync($cuisineIds);

        $tagIds = collect($data['tags'])->map(
            fn (string $name) => $this->tagIds[$name] ??= Tag::firstOrCreate(['name' => $name])->id
        );
        $restaurant->tags()->sync($tagIds);

        $this->applyHalalHeuristic($restaurant);

        return $restaurant;
    }

    /**
     * Cheap in-memory evaluation first; only touches the ledger (row lock + transaction) when
     * there's something to assert or a previous automatic non_halal to retract. The verification
     * service itself refuses to overwrite any human-reviewed decision.
     */
    private function applyHalalHeuristic(Restaurant $restaurant): void
    {
        $result = HalalHeuristic::evaluate([
            'name' => $restaurant->name,
            'signature_dish' => $restaurant->signature_dish,
            'food_category' => $restaurant->food_category,
            'google_types' => $restaurant->google_types,
        ]);

        if ($result->likelyNonHalal || $restaurant->halal_status === HalalStatus::NonHalal) {
            $this->halalVerifications->recordHeuristic($restaurant, $result);
            $restaurant->refresh();
        } elseif (NonHalalSecondOpinionGate::shouldAsk($restaurant, $result)) {
            // Weak-only keyword match with enough context: queue an AI second opinion. Admin
            // review hint only — never a status change.
            SecondOpinionNonHalal::dispatchIfDue($restaurant);
        }
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

        return $this->boundingBoxQuery('google', $latitude, $longitude, $radiusKm)
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
        return $this->boundingBoxQuery('user_submitted', $latitude, $longitude, $radiusKm)
            ->get()
            ->filter(fn (Restaurant $restaurant) => RecommendationService::distanceKm(
                $latitude, $longitude, (float) $restaurant->latitude, (float) $restaurant->longitude
            ) <= $radiusKm)
            ->map(fn (Restaurant $restaurant) => $restaurant->toRecommendationArray())
            ->values()
            ->all();
    }

    /**
     * Degree deltas for a lat/lng bounding box around a point — a cheap, index-able SQL
     * pre-filter; callers trim the box corners with an exact Haversine check.
     *
     * @return array{0: float, 1: float} [latDelta, lngDelta]
     */
    private static function boundingBoxDeltas(float $latitude, float $radiusKm): array
    {
        return [
            $radiusKm / 111.0,
            $radiusKm / (111.320 * max(cos(deg2rad($latitude)), 0.01)),
        ];
    }

    /**
     * SQL-level pre-filter shared by both "restaurants near a point" readers — a degree-delta
     * bounding box on lat/lng, cheap and index-able (see the `(provider, is_active, latitude)`
     * index), narrowing what actually gets fetched before the exact Haversine `->filter()` in
     * each caller trims the box's corners down to the real circle. Column list matches exactly
     * what `Restaurant::toRecommendationArray()` reads (including `opening_hours`, needed by
     * `openStatus()` — NOT excludable despite not appearing directly in that method's return
     * array) — `address`/timestamps are the only columns genuinely unused, so those are the
     * only ones left out here.
     */
    private function boundingBoxQuery(string $provider, float $latitude, float $longitude, float $radiusKm): Builder
    {
        [$latDelta, $lngDelta] = self::boundingBoxDeltas($latitude, $radiusKm);

        return Restaurant::where('provider', $provider)
            ->where('is_active', true)
            ->whereBetween('latitude', [$latitude - $latDelta, $latitude + $latDelta])
            ->whereBetween('longitude', [$longitude - $lngDelta, $longitude + $lngDelta])
            ->select([
                'id', 'name', 'address', 'latitude', 'longitude', 'price_level', 'rating', 'opening_hours',
                'is_active', 'provider', 'provider_place_id', 'food_category', 'signature_dish',
                'google_types', 'phone', 'instagram_handle', 'tiktok_handle', 'website_url',
                'user_rating_count', 'impressions_count', 'accepted_count', 'rejected_count',
                'halal_status', 'halal_review_state', 'halal_expires_at', 'halal_active_certificate_id',
            ])
            ->with(['cuisines:id,slug', 'tags:id,name', 'activeHalalCertificate:id,authority']);
    }
}
