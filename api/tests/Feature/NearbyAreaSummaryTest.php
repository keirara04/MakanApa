<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NearbyAreaSummaryTest extends TestCase
{
    use RefreshDatabase;

    /** Same box shape as NearbyPlacesTest — loosely covers a small cluster of test restaurants. */
    private const BOX = [
        'north' => 2.940, 'south' => 2.918, 'east' => 101.800, 'west' => 101.770,
    ];

    private function makeRestaurant(array $overrides = []): Restaurant
    {
        return Restaurant::create(array_merge([
            'name' => 'Test Place',
            'latitude' => 2.930,
            'longitude' => 101.785,
            'is_active' => true,
            'provider' => 'google',
            'provider_place_id' => 'places/'.uniqid(),
            'opening_hours' => ['open_now' => true],
        ], $overrides));
    }

    public function test_area_summary_counts_and_categories_reflect_the_same_filtered_set_as_the_markers(): void
    {
        $this->makeRestaurant(['name' => 'Mamak A', 'food_category' => 'Mamak', 'price_level' => 1, 'rating' => 4.2]);
        $this->makeRestaurant(['name' => 'Mamak B', 'food_category' => 'Mamak', 'price_level' => 2, 'rating' => 4.5]);
        $this->makeRestaurant(['name' => 'Mamak C', 'food_category' => 'Mamak', 'price_level' => 2, 'rating' => 4.9]);
        $this->makeRestaurant(['name' => 'Fancy Bistro', 'food_category' => 'Western', 'price_level' => 3, 'rating' => 4.0, 'opening_hours' => ['open_now' => false]]);

        $response = $this->getJson('/api/v1/places/nearby?'.http_build_query(self::BOX))->assertOk();

        $summary = $response->json('areaSummary');
        $places = $response->json('places');

        $this->assertSame(count($places), $summary['placeCount']);
        $this->assertSame(3, $summary['openNowCount']);
        // price_level <= 2 (the app's one existing "budget-friendly" threshold): all 3 Mamak, not the RM Western.
        $this->assertSame(3, $summary['budgetFriendlyCount']);
        $this->assertSame(['label' => 'Mamak', 'count' => 3], $summary['topCategories'][0]);

        $topRatedNames = collect($summary['topRated'])->pluck('name')->all();
        $this->assertContains('Mamak C', $topRatedNames);
        $this->assertSame('Mamak C', $summary['topRated'][0]['name']);
    }

    public function test_personality_tags_require_a_minimum_sample_size(): void
    {
        // Only 2 places, both budget-friendly — 100% ratio, but below the 5-place minimum.
        $this->makeRestaurant(['food_category' => 'Cafe', 'price_level' => 1]);
        $this->makeRestaurant(['food_category' => 'Cafe', 'price_level' => 1]);

        $response = $this->getJson('/api/v1/places/nearby?'.http_build_query(self::BOX))->assertOk();

        $this->assertSame([], $response->json('areaSummary.personalityTags'));
    }

    public function test_personality_tags_appear_once_the_minimum_sample_size_is_met(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->makeRestaurant(['food_category' => 'Cafe', 'price_level' => 1]);
        }

        $response = $this->getJson('/api/v1/places/nearby?'.http_build_query(self::BOX))->assertOk();

        $tags = collect($response->json('areaSummary.personalityTags'))->pluck('key');
        $this->assertTrue($tags->contains('budget_friendly'));
        $this->assertTrue($tags->contains('category_heavy'));
    }

    public function test_community_finds_are_ordered_by_distance_from_the_viewport_center_not_the_user(): void
    {
        $centerLat = (self::BOX['north'] + self::BOX['south']) / 2;
        $centerLon = (self::BOX['east'] + self::BOX['west']) / 2;

        $near = $this->makeRestaurant([
            'name' => 'Near Community Find', 'provider' => 'user_submitted',
            'latitude' => $centerLat + 0.001, 'longitude' => $centerLon,
        ]);
        $far = $this->makeRestaurant([
            'name' => 'Far Community Find', 'provider' => 'user_submitted',
            'latitude' => $centerLat + 0.008, 'longitude' => $centerLon,
        ]);
        // A regular Google-sourced place must never appear in communityFinds.
        $this->makeRestaurant(['name' => 'Google Place', 'provider' => 'google']);

        $response = $this->getJson('/api/v1/places/nearby?'.http_build_query(self::BOX))->assertOk();
        $communityFinds = collect($response->json('areaSummary.communityFinds'));

        $this->assertFalse($communityFinds->pluck('name')->contains('Google Place'));
        $ids = $communityFinds->pluck('id')->unique()->values();
        $this->assertSame($near->id, $ids->first());
        $this->assertTrue($ids->contains($far->id));
    }

    public function test_zero_places_returns_empty_summary_with_no_personality_tags(): void
    {
        $response = $this->getJson('/api/v1/places/nearby?'.http_build_query(self::BOX))->assertOk();

        $summary = $response->json('areaSummary');
        $this->assertSame(0, $summary['placeCount']);
        $this->assertSame([], $summary['topRated']);
        $this->assertSame([], $summary['communityFinds']);
        $this->assertSame([], $summary['personalityTags']);
    }
}
