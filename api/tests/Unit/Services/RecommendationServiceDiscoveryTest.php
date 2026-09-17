<?php

namespace Tests\Unit\Services;

use App\Services\Craving\CravingResolver;
use App\Services\RecommendationService;
use App\Support\DiscoveryMode;
use PHPUnit\Framework\TestCase;

/**
 * Kept as a plain PHPUnit TestCase (no Laravel bootstrap) — same as RecommendationServiceTest —
 * since RecommendationService itself stays framework-independent (all config-shaped values are
 * passed in via $preference, never read via the config() helper directly).
 */
class RecommendationServiceDiscoveryTest extends TestCase
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
            'impressions_count' => 0,
            'accepted_count' => 0,
            'rejected_count' => 0,
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

    public function test_relevance_tier_gates_ranking_before_discovery_mode_signal(): void
    {
        // Regression guard for the "popular burger place outranks the actual ice cream match"
        // failure mode — a huge community/accepted signal must never let a non-matching
        // candidate outrank one that actually matches the recognized craving.
        $cravingResolver = new CravingResolver;
        $iceCreamShop = $this->makeRestaurant(['id' => 1, 'name' => 'Inside Scoop', 'food_category' => 'dessert', 'rating' => 3.0]);
        $popularBurger = $this->makeRestaurant([
            'id' => 2, 'name' => 'Viral Burger Joint', 'rating' => 5.0,
            'impressions_count' => 1000, 'accepted_count' => 950,
        ]);

        $preference = $this->makePreference([
            'cravingIntent' => $cravingResolver->resolve('ice cream'),
            'discoveryMode' => DiscoveryMode::Normal,
        ]);

        $ranked = $this->service->topCandidates([$iceCreamShop, $popularBurger], $preference);

        $this->assertSame(1, $ranked[0]['restaurant']['id'], 'The exact match must rank first regardless of the other candidate\'s community signal.');
    }

    public function test_community_score_uses_prior_for_zero_impressions(): void
    {
        $restaurant = $this->makeRestaurant(['id' => 1, 'impressions_count' => 0, 'accepted_count' => 0]);
        $preference = $this->makePreference(['discoveryMode' => DiscoveryMode::Normal]);

        $breakdown = $this->service->scoreBreakdown($restaurant, $preference, 0);

        $this->assertEqualsWithDelta(0.5, $breakdown['components']['community'], 0.0001);
    }

    public function test_community_score_moves_toward_true_ratio_as_impressions_grow(): void
    {
        // The concrete case that motivated Bayesian smoothing over a raw ratio: 1/1 (100%)
        // must NOT score higher than 70/100 (70%) just because of small-sample noise.
        $tinySample = $this->makeRestaurant(['id' => 1, 'impressions_count' => 1, 'accepted_count' => 1]);
        $largeSample = $this->makeRestaurant(['id' => 2, 'impressions_count' => 100, 'accepted_count' => 70]);
        $preference = $this->makePreference(['discoveryMode' => DiscoveryMode::Normal]);

        $tinyScore = $this->service->scoreBreakdown($tinySample, $preference, 0)['components']['community'];
        $largeScore = $this->service->scoreBreakdown($largeSample, $preference, 0)['components']['community'];

        $this->assertGreaterThan($tinyScore, $largeScore);
    }

    public function test_discovery_overlay_is_absent_when_no_discovery_mode_given(): void
    {
        // Backward-compatibility guarantee: a caller that never sets 'discoveryMode' (every
        // pre-existing test, and any future caller that hasn't opted in) gets byte-identical
        // scoring to before this feature existed — no surprise 'community' component appears.
        $restaurant = $this->makeRestaurant();
        $preference = $this->makePreference();

        $breakdown = $this->service->scoreBreakdown($restaurant, $preference, 0);

        $this->assertArrayNotHasKey('community', $breakdown['components']);
    }

    public function test_personal_fit_rewards_matching_price_and_category(): void
    {
        $matching = $this->makeRestaurant(['id' => 1, 'price_level' => 1, 'food_category' => 'cafe']);
        $nonMatching = $this->makeRestaurant(['id' => 2, 'price_level' => 3, 'food_category' => 'steak']);
        $history = ['price_levels' => [1], 'food_categories' => ['cafe']];
        $preference = $this->makePreference(['installationHistory' => $history]);

        $matchingScore = $this->service->scoreBreakdown($matching, $preference, 0)['components']['personalFit'];
        $nonMatchingScore = $this->service->scoreBreakdown($nonMatching, $preference, 0)['components']['personalFit'];

        $this->assertGreaterThan($nonMatchingScore, $matchingScore);
    }

    public function test_personal_fit_is_absent_without_installation_history(): void
    {
        $restaurant = $this->makeRestaurant();
        $preference = $this->makePreference();

        $breakdown = $this->service->scoreBreakdown($restaurant, $preference, 0);

        $this->assertArrayNotHasKey('personalFit', $breakdown['components']);
    }
}
