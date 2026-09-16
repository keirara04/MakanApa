<?php

namespace Tests\Unit\Services\Places;

use App\Services\Places\PlaceNormalizer;
use App\Services\Places\ProviderPlace;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PlaceNormalizerTest extends TestCase
{
    private function makePlace(array $types, string $name = 'Test Place'): ProviderPlace
    {
        return new ProviderPlace(
            providerPlaceId: 'abc123',
            name: $name,
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

    /** @return array<string, array{0: string, 1: string}> name => expected tag */
    public static function dishNameProvider(): array
    {
        return [
            'nasi kandar' => ['Nasi Kandar Pelita', 'nasi_kandar'],
            'ayam gepuk' => ['Ayam Gepuk Pak Gembus KL', 'ayam_gepuk'],
            'nasi padang' => ['Restoran Nasi Padang Minang', 'nasi_padang'],
            'mee goreng' => ['Warung Mee Goreng Mamak', 'mee_goreng'],
            'nasi lemak' => ['Nasi Lemak Antarabangsa', 'nasi_lemak'],
            'char kuey teow (kuey spelling)' => ['Penang Char Kuey Teow', 'char_kuey_teow'],
            'char kuey teow (koay spelling, different case)' => ['CHAR KOAY TEOW STALL', 'char_kuey_teow'],
            'banana leaf rice' => ['Banana Leaf Rice House', 'banana_leaf_rice'],
            'dim sum' => ['Dim Sum Corner', 'dim_sum'],
        ];
    }

    #[DataProvider('dishNameProvider')]
    public function test_dish_alias_matches_restaurant_name(string $name, string $expectedTag): void
    {
        $normalizer = new PlaceNormalizer;
        $result = $normalizer->normalize(collect([$this->makePlace(['restaurant'], $name)]))->first();

        $this->assertContains($expectedTag, $result['tags']);
    }

    public function test_near_miss_name_does_not_falsely_tag_a_dish(): void
    {
        $normalizer = new PlaceNormalizer;
        // Mentions "Padang" (a place in Sumatra) but isn't a Nasi Padang restaurant, and
        // has no other dish alias in its name.
        $result = $normalizer->normalize(collect([$this->makePlace(['restaurant'], 'Padang Besar Cafe')]))->first();

        $this->assertSame([], $result['tags']);
    }

    public function test_name_with_no_dish_alias_produces_no_dish_tags(): void
    {
        $normalizer = new PlaceNormalizer;
        $result = $normalizer->normalize(collect([$this->makePlace(['restaurant'], 'Restoran Kak Ani')]))->first();

        $this->assertSame([], $result['tags']);
    }
}
