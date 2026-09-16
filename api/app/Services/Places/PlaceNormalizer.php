<?php

namespace App\Services\Places;

use Illuminate\Support\Collection;

/**
 * The only thing that knows Google -> MakanApa semantics. Deliberately
 * conservative: maps Google place *types* to MakanApa's tag/cuisine
 * vocabulary, never infers from restaurant names. Being uncertain (fewer
 * tags) beats confidently assigning wrong ones.
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

        return [
            'provider' => 'google',
            'provider_place_id' => $place->providerPlaceId,
            'name' => $place->name,
            'latitude' => $place->latitude,
            'longitude' => $place->longitude,
            'price_level' => $place->priceLevel,
            'rating' => $place->rating,
            'is_active' => true,
            // Snapshot of Google's open-now signal at sync time — re-synced whenever the
            // place_sync_areas cache window expires. Not full weekly-hours parsing (Phase 3 scope).
            'opening_hours' => ['open_now' => $place->openNow],
            'cuisines' => array_values(array_unique($cuisines)),
            'tags' => array_values(array_unique($tags)),
        ];
    }
}
