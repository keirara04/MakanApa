<?php

namespace App\Services\Places;

use App\Support\FoodTaxonomy;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * The only thing that knows Google -> MakanApa semantics. Deliberately
 * conservative: maps Google place *types* to MakanApa's tag/cuisine
 * vocabulary, never infers from restaurant names. Being uncertain (fewer
 * tags) beats confidently assigning wrong ones.
 *
 * One narrow, deliberate exception: FoodTaxonomy's curated Malaysian-dish
 * aliases ARE matched against the place name (see dishTagsFor()) so specific
 * dishes like "Nasi Kandar" can be tagged even though Google's types[] has no
 * dish-level granularity. This does not extend to cuisines/tags/food-category
 * in general — those still come from types[] only.
 */
class PlaceNormalizer
{
    /** @var array<string, array{cuisines: string[], tags: string[]}> */
    private const TYPE_MAP = [
        'chinese_restaurant' => ['cuisines' => ['chinese'], 'tags' => []],
        'japanese_restaurant' => ['cuisines' => ['japanese'], 'tags' => []],
        'ramen_restaurant' => ['cuisines' => ['japanese'], 'tags' => ['comfort_food']],
        'sushi_restaurant' => ['cuisines' => ['japanese'], 'tags' => []],
        'korean_restaurant' => ['cuisines' => ['korean'], 'tags' => []],
        'thai_restaurant' => ['cuisines' => ['thai'], 'tags' => ['spicy']],
        'indian_restaurant' => ['cuisines' => ['indian'], 'tags' => ['spicy']],
        'malaysian_restaurant' => ['cuisines' => ['malay'], 'tags' => ['comfort_food']],
        'indonesian_restaurant' => ['cuisines' => ['malay'], 'tags' => []],
        'fast_food_restaurant' => ['cuisines' => [], 'tags' => ['quick', 'cheap']],
        'cafe' => ['cuisines' => ['western'], 'tags' => ['cafe']],
        'vegetarian_restaurant' => ['cuisines' => [], 'tags' => ['healthy', 'vegetarian']],
        'vegan_restaurant' => ['cuisines' => [], 'tags' => ['healthy', 'vegetarian']],
    ];

    /**
     * Dish/category-style classification, separate from the nationality-cuisine map above —
     * a burger place has no cuisine to fall back to, but it does have a food category.
     * Ordered by specificity, most specific first: Google's types[] array order isn't
     * guaranteed, so this list — not iteration order — is the priority when a place has
     * multiple matching types (e.g. both `fast_food_restaurant` and `hamburger_restaurant`).
     *
     * @var array<string, string>
     */
    private const FOOD_CATEGORY_MAP = [
        'hamburger_restaurant' => 'burger',
        'chicken_restaurant' => 'chicken',
        'pizza_restaurant' => 'pizza',
        'sandwich_shop' => 'sandwich',
        'ramen_restaurant' => 'ramen',
        'sushi_restaurant' => 'sushi',
        'seafood_restaurant' => 'seafood',
        'steak_house' => 'steak',
        'barbecue_restaurant' => 'bbq',
        'bakery' => 'bakery',
        'dessert_restaurant' => 'dessert',
        'ice_cream_shop' => 'dessert',
        'coffee_shop' => 'cafe',
        'bar' => 'drinks',
        'pub' => 'drinks',
        'breakfast_restaurant' => 'breakfast',
        'brunch_restaurant' => 'breakfast',
        'fast_food_restaurant' => 'fast_food',
    ];

    /**
     * @param  Collection<int, ProviderPlace>  $places
     * @return Collection<int, array<string, mixed>> normalized, unpersisted restaurant data
     */
    public function normalize(Collection $places): Collection
    {
        return $places->map(fn (ProviderPlace $place) => $this->normalizeOne($place));
    }

    private function normalizeOne(ProviderPlace $place): array
    {
        $cuisines = [];
        $tags = [];

        foreach ($place->types as $type) {
            if (isset(self::TYPE_MAP[$type])) {
                $cuisines = array_merge($cuisines, self::TYPE_MAP[$type]['cuisines']);
                $tags = array_merge($tags, self::TYPE_MAP[$type]['tags']);
            }
        }

        $foodCategory = $this->foodCategoryFor($place->types);
        $tags = array_merge($tags, $this->dishTagsFor($place->name));

        return [
            'provider' => 'google',
            'provider_place_id' => $place->providerPlaceId,
            'name' => $place->name,
            'latitude' => $place->latitude,
            'longitude' => $place->longitude,
            'price_level' => $place->priceLevel,
            'rating' => $place->rating,
            'user_rating_count' => $place->userRatingCount,
            'is_active' => true,
            // Snapshot of Google's open-now signal at sync time — re-synced whenever the
            // place_sync_areas cache window expires. Not full weekly-hours parsing (Phase 3 scope).
            'opening_hours' => ['open_now' => $place->openNow],
            'cuisines' => array_values(array_unique($cuisines)),
            'tags' => array_values(array_unique($tags)),
            'food_category' => $foodCategory,
            // Raw Google types, kept alongside the curated cuisines/tags/food_category above —
            // this is what PlacesService::readGoogleRestaurantsNear() filters by per DiscoveryMode,
            // since the curated fields are lossy and shouldn't be reverse-engineered for that.
            'google_types' => $place->types,
        ];
    }

    /**
     * Winner-only presentation data (photos/reviews) — transient, never persisted. Separate
     * from normalize()/normalizeOne() above, which only ever handle search-candidate data.
     *
     * @param  array<string, mixed>  $raw  decoded JSON from GooglePlacesProvider::fetchPresentationDetails()
     * @return array{photos: array, reviews: array, placeGoogleMapsUrl: ?string, closesAt: ?string}
     */
    public function normalizePresentationDetails(array $raw): array
    {
        // Up to 5 photos (matches the "1/5" carousel), each keeping its own attributions —
        // a business-uploaded photo and a contributor photo aren't credited the same way.
        $photos = collect($raw['photos'] ?? [])->take(5)->map(fn (array $p) => [
            'name' => $p['name'], // transient Google photo resource name — used immediately, never stored
            'authorAttributions' => collect($p['authorAttributions'] ?? [])->map(fn (array $a) => [
                'name' => $a['displayName'] ?? null,
                'profileUrl' => $a['uri'] ?? null,
                'photoUrl' => $a['photoUri'] ?? null,
            ])->all(),
            'googleMapsUrl' => $p['googleMapsUri'] ?? null,
            'flagContentUrl' => $p['flagContentUri'] ?? null,
        ])->all();

        $reviews = collect($raw['reviews'] ?? [])->take(2)->map(fn (array $r) => [
            'text' => $r['text']['text'] ?? '',
            'rating' => $r['rating'] ?? null,
            'authorName' => $r['authorAttribution']['displayName'] ?? 'Google user',
            'authorProfileUrl' => $r['authorAttribution']['uri'] ?? null,
            'authorPhotoUrl' => $r['authorAttribution']['photoUri'] ?? null,
            'relativePublishTime' => $r['relativePublishTimeDescription'] ?? null,
            'googleMapsUrl' => $r['googleMapsUri'] ?? null,
            'flagContentUrl' => $r['flagContentUri'] ?? null,
        ])->all();

        return [
            'photos' => $photos,
            'reviews' => $reviews,
            'placeGoogleMapsUrl' => $raw['googleMapsUri'] ?? null,
            'closesAt' => $this->formatClosesAt($raw),
        ];
    }

    /**
     * "10:00 PM" in the venue's own local time. nextCloseTime is UTC; utcOffsetMinutes
     * (the venue's offset, requested alongside it) converts it correctly rather than
     * assuming the server's timezone matches the restaurant's.
     */
    private function formatClosesAt(array $raw): ?string
    {
        $nextCloseTime = $raw['currentOpeningHours']['nextCloseTime'] ?? null;
        if ($nextCloseTime === null) {
            return null;
        }

        $offsetMinutes = $raw['utcOffsetMinutes'] ?? 0;

        return Carbon::parse($nextCloseTime)->addMinutes($offsetMinutes)->format('g:i A');
    }

    /**
     * Walks FOOD_CATEGORY_MAP in its defined priority order (most specific first) and
     * returns the first category whose Google type is present on this place — not the
     * first type in $types order, since Google doesn't guarantee that ordering.
     *
     * @param  string[]  $types
     */
    private function foodCategoryFor(array $types): ?string
    {
        foreach (self::FOOD_CATEGORY_MAP as $type => $category) {
            if (in_array($type, $types, true)) {
                return $category;
            }
        }

        return null;
    }

    /**
     * The one place that infers from a restaurant's name — see class doc for why this is a
     * deliberate, narrow exception. Returns every dish tag whose alias appears in $name, not
     * just the first, since a name can plausibly mention more than one dish.
     *
     * @return string[]
     */
    private function dishTagsFor(string $name): array
    {
        $tags = [];
        foreach (FoodTaxonomy::aliasPatterns() as $tag => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $name) === 1) {
                    $tags[] = $tag;
                    break;
                }
            }
        }

        return $tags;
    }
}
