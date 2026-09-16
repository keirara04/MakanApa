<?php

namespace Tests\Unit\Services\Places;

use App\Services\Places\PlaceNormalizer;
use App\Services\Places\ProviderPlace;
use PHPUnit\Framework\TestCase;

class PlaceNormalizerTest extends TestCase
{
    private function makePlace(array $types): ProviderPlace
    {
        return new ProviderPlace(
            providerPlaceId: 'abc123',
            name: 'Test Place',
            latitude: 1.0,
            longitude: 2.0,
            types: $types,
            rating: null,
            priceLevel: null,
            openNow: null,
        );
    }

    public function test_maps_hamburger_restaurant_to_burger_food_category(): void
    {
        $normalizer = new PlaceNormalizer;
        $result = $normalizer->normalize(collect([$this->makePlace(['hamburger_restaurant', 'restaurant'])]))->first();

        $this->assertSame('burger', $result['food_category']);
        // BGM Burgerman's actual bug: no cuisine maps from this type either.
        $this->assertSame([], $result['cuisines']);
    }

    public function test_specificity_order_wins_over_types_array_order(): void
    {
        // fast_food_restaurant appears first in $types, but hamburger_restaurant is more
        // specific and must win regardless of array position.
        $normalizer = new PlaceNormalizer;
        $result = $normalizer->normalize(collect([
            $this->makePlace(['fast_food_restaurant', 'hamburger_restaurant']),
        ]))->first();

        $this->assertSame('burger', $result['food_category']);
    }

    public function test_chicken_restaurant_stays_conservative_not_fried(): void
    {
        $normalizer = new PlaceNormalizer;
        $result = $normalizer->normalize(collect([$this->makePlace(['chicken_restaurant'])]))->first();

        $this->assertSame('chicken', $result['food_category']);
    }

    public function test_unmapped_type_produces_null_food_category(): void
    {
        $normalizer = new PlaceNormalizer;
        $result = $normalizer->normalize(collect([$this->makePlace(['restaurant'])]))->first();

        $this->assertNull($result['food_category']);
    }
}
