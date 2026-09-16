<?php

namespace Tests\Unit\Services;

use App\Services\RecommendationService;
use PHPUnit\Framework\TestCase;

class RecommendationServiceTest extends TestCase
{
    private const ORIGIN_LAT = 2.928400;
    private const ORIGIN_LNG = 101.780200;

    private RecommendationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RecommendationService;
    }

    private function makeRestaurant(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'latitude' => self::ORIGIN_LAT,
            'longitude' => self::ORIGIN_LNG,
            'price_level' => 1,
            'rating' => 4.0,
            'is_active' => true,
            'open_status' => 'unknown',
            'cuisines' => [],
            'tags' => [],
        ], $overrides);
    }

    private function makePreference(array $overrides = []): array
    {
        return array_merge([
            'moodTags' => [],
            'cuisines' => [],
            'budgetMax' => 3,
            'maxDistanceKm' => 2.0,
            'latitude' => self::ORIGIN_LAT,
            'longitude' => self::ORIGIN_LNG,
        ], $overrides);
    }

    public function test_excludes_restaurant_beyond_max_distance(): void
    {
        $near = $this->makeRestaurant(['id' => 1, 'latitude' => self::ORIGIN_LAT + 0.001]);
        $far = $this->makeRestaurant(['id' => 2, 'latitude' => self::ORIGIN_LAT + 0.5]);
        $preference = $this->makePreference(['maxDistanceKm' => 2.0]);

        $eligible = $this->service->eligibleRestaurants([$near, $far], $preference);
        $ids = array_column(array_column($eligible, 'restaurant'), 'id');

        $this->assertContains(1, $ids);
        $this->assertNotContains(2, $ids);
    }

    public function test_excludes_restaurant_over_budget(): void
    {
        $affordable = $this->makeRestaurant(['id' => 1, 'price_level' => 1]);
        $expensive = $this->makeRestaurant(['id' => 2, 'price_level' => 3]);
        $preference = $this->makePreference(['budgetMax' => 1]);

        $eligible = $this->service->eligibleRestaurants([$affordable, $expensive], $preference);
        $ids = array_column(array_column($eligible, 'restaurant'), 'id');

        $this->assertContains(1, $ids);
        $this->assertNotContains(2, $ids);
    }

    public function test_null_budget_max_means_no_price_filter(): void
    {
        $expensive = $this->makeRestaurant(['id' => 1, 'price_level' => 3]);
        $preference = $this->makePreference(['budgetMax' => null]);

        $eligible = $this->service->eligibleRestaurants([$expensive], $preference);
        $ids = array_column(array_column($eligible, 'restaurant'), 'id');

        $this->assertContains(1, $ids);
    }

    public function test_excludes_inactive_restaurant(): void
    {
        $active = $this->makeRestaurant(['id' => 1, 'is_active' => true]);
        $inactive = $this->makeRestaurant(['id' => 2, 'is_active' => false]);
        $preference = $this->makePreference();

        $eligible = $this->service->eligibleRestaurants([$active, $inactive], $preference);
        $ids = array_column(array_column($eligible, 'restaurant'), 'id');

        $this->assertContains(1, $ids);
        $this->assertNotContains(2, $ids);
    }

    public function test_excludes_closed_restaurant(): void
    {
        $open = $this->makeRestaurant(['id' => 1, 'open_status' => 'open']);
        $closed = $this->makeRestaurant(['id' => 2, 'open_status' => 'closed']);
        $preference = $this->makePreference();

        $eligible = $this->service->eligibleRestaurants([$open, $closed], $preference);
        $ids = array_column(array_column($eligible, 'restaurant'), 'id');

        $this->assertContains(1, $ids);
        $this->assertNotContains(2, $ids);
    }

    public function test_includes_unknown_open_status_restaurant(): void
    {
        $unknown = $this->makeRestaurant(['id' => 1, 'open_status' => 'unknown']);
        $preference = $this->makePreference();

        $eligible = $this->service->eligibleRestaurants([$unknown], $preference);
        $ids = array_column(array_column($eligible, 'restaurant'), 'id');

        $this->assertContains(1, $ids);
    }

    public function test_mood_matching_restaurant_scores_higher_than_non_matching(): void
    {
        $matching = $this->makeRestaurant(['id' => 1, 'tags' => ['comfort_food', 'spicy']]);
        $nonMatching = $this->makeRestaurant(['id' => 2, 'tags' => ['quick']]);
        $preference = $this->makePreference(['moodTags' => ['comfort_food', 'spicy']]);

        $scoreMatching = $this->service->score($matching, $preference, 0);
        $scoreNonMatching = $this->service->score($nonMatching, $preference, 0);

        $this->assertGreaterThan($scoreNonMatching, $scoreMatching);
    }

    public function test_anything_preference_normalizes_weights_to_100_max(): void
    {
        $restaurant = $this->makeRestaurant(['rating' => 5.0]);
        $preference = $this->makePreference();

        $score = $this->service->score($restaurant, $preference, 0);

        $this->assertEqualsWithDelta(100.0, $score, 0.001);
    }

    public function test_reroll_excludes_current_pick(): void
    {
        $candidates = array_map(
            fn ($i) => ['restaurant' => $this->makeRestaurant(['id' => $i]), 'score' => 100 - $i],
            range(1, 5)
        );
        $current = $candidates[0];

        for ($i = 0; $i < 20; $i++) {
            $reroll = $this->service->pick($candidates, $current);
            $this->assertNotEquals($current['restaurant']['id'], $reroll['restaurant']['id']);
        }
    }
}
