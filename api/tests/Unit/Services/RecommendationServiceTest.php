<?php

namespace Tests\Unit\Services;

use App\Services\Craving\CravingResolver;
use App\Services\RecommendationService;
use PHPUnit\Framework\TestCase;

class RecommendationServiceTest extends TestCase
{
    private const ORIGIN_LAT = 2.928400;

    private const ORIGIN_LNG = 101.780200;

    private RecommendationService $service;

    private CravingResolver $cravingResolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RecommendationService;
        $this->cravingResolver = new CravingResolver; // no AI parser configured — taxonomy-only, deterministic
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

    public function test_curated_dish_tag_scores_higher_than_non_matching(): void
    {
        $matching = $this->makeRestaurant(['id' => 1, 'tags' => ['nasi_kandar']]);
        $nonMatching = $this->makeRestaurant(['id' => 2, 'tags' => ['quick']]);
        $preference = $this->makePreference(['moodTags' => ['nasi_kandar']]);

        $scoreMatching = $this->service->score($matching, $preference, 0);
        $scoreNonMatching = $this->service->score($nonMatching, $preference, 0);

        $this->assertGreaterThan($scoreNonMatching, $scoreMatching);
    }

    public function test_custom_craving_matching_name_scores_higher_than_non_matching(): void
    {
        $matching = $this->makeRestaurant(['id' => 1, 'name' => 'Nasi Kandar Pelita']);
        $nonMatching = $this->makeRestaurant(['id' => 2, 'name' => 'Sushi Kin']);
        $preference = $this->makePreference(['cravingIntent' => $this->cravingResolver->resolve('nasi kandar')]);

        $scoreMatching = $this->service->score($matching, $preference, 0);
        $scoreNonMatching = $this->service->score($nonMatching, $preference, 0);

        $this->assertGreaterThan($scoreNonMatching, $scoreMatching);
    }

    public function test_custom_craving_matches_via_dish_tag_even_without_name_hit(): void
    {
        $tagged = $this->makeRestaurant(['id' => 1, 'name' => 'Restoran Kak Ani', 'tags' => ['nasi_kandar']]);
        $untagged = $this->makeRestaurant(['id' => 2, 'name' => 'Sushi Kin']);
        $preference = $this->makePreference(['cravingIntent' => $this->cravingResolver->resolve('nasi kandar')]);

        $scoreTagged = $this->service->score($tagged, $preference, 0);
        $scoreUntagged = $this->service->score($untagged, $preference, 0);

        $this->assertGreaterThan($scoreUntagged, $scoreTagged);
    }

    public function test_ice_cream_craving_beats_higher_rated_non_matching_burger(): void
    {
        // Regression test for the original bug: searching "ice cream" returned a burger place
        // because craving contributed zero signal and ranking fell through to rating/distance.
        $iceCreamShop = $this->makeRestaurant(['id' => 1, 'name' => 'Inside Scoop', 'food_category' => 'dessert', 'rating' => 4.0]);
        $burger = $this->makeRestaurant(['id' => 2, 'name' => 'Best Burger In Town', 'rating' => 5.0]);
        $preference = $this->makePreference(['cravingIntent' => $this->cravingResolver->resolve('ice cream')]);

        $scoreIceCream = $this->service->score($iceCreamShop, $preference, 0);
        $scoreBurger = $this->service->score($burger, $preference, 0);

        $this->assertGreaterThan($scoreBurger, $scoreIceCream);
    }

    public function test_unmatched_custom_craving_falls_back_to_other_factors_without_crashing(): void
    {
        $restaurant = $this->makeRestaurant(['id' => 1, 'name' => 'Sushi Kin', 'rating' => 5.0]);
        $preference = $this->makePreference(['cravingIntent' => $this->cravingResolver->resolve('roti john cheese banjir')]);

        $score = $this->service->score($restaurant, $preference, 0);

        $this->assertGreaterThan(0, $score);
    }

    public function test_unresolved_craving_still_credits_a_restaurant_the_raw_text_matches(): void
    {
        // Regression test: "roti john" doesn't resolve to any taxonomy concept, but
        // PlacesService's Text Search still fetches candidates using the raw text as a
        // fallback query — scoring must be able to credit a restaurant that literally matches
        // it, otherwise that Text Search call finds the right place and then discards the
        // signal entirely (reproducing the original ice-cream-vs-burger bug for anything
        // outside the taxonomy).
        $rotiJohn = $this->makeRestaurant(['id' => 1, 'name' => 'Restoran Roti John Best', 'rating' => 4.0]);
        $unrelated = $this->makeRestaurant(['id' => 2, 'name' => 'Sushi Kin', 'rating' => 5.0]);
        $preference = $this->makePreference(['cravingIntent' => $this->cravingResolver->resolve('roti john')]);

        $scoreMatching = $this->service->score($rotiJohn, $preference, 0);
        $scoreNonMatching = $this->service->score($unrelated, $preference, 0);

        $this->assertGreaterThan($scoreNonMatching, $scoreMatching);
    }

    public function test_recommend_reports_craving_matched_true_when_a_candidate_matches(): void
    {
        $matching = $this->makeRestaurant(['id' => 1, 'name' => 'Nasi Kandar Pelita']);
        $preference = $this->makePreference(['cravingIntent' => $this->cravingResolver->resolve('nasi kandar')]);

        $result = $this->service->recommend([$matching], $preference);

        $this->assertSame('nasi kandar', $result['craving']['query']);
        $this->assertTrue($result['craving']['matched']);
        $this->assertSame('nasi_kandar', $result['craving']['resolvedAs']);
        $this->assertSame('taxonomy', $result['craving']['source']);
    }

    public function test_recommend_reports_craving_matched_false_when_nothing_matches(): void
    {
        $restaurant = $this->makeRestaurant(['id' => 1, 'name' => 'Sushi Kin']);
        $preference = $this->makePreference(['cravingIntent' => $this->cravingResolver->resolve('roti john cheese banjir')]);

        $result = $this->service->recommend([$restaurant], $preference);

        $this->assertSame('roti john cheese banjir', $result['craving']['query']);
        $this->assertFalse($result['craving']['matched']);
        $this->assertNotNull($result['pick']);
    }

    public function test_recommend_reports_null_craving_when_none_submitted(): void
    {
        $restaurant = $this->makeRestaurant(['id' => 1]);
        $preference = $this->makePreference();

        $result = $this->service->recommend([$restaurant], $preference);

        $this->assertNull($result['craving']);
    }

    public function test_quick_and_healthy_tags_still_score_via_tag_match_component(): void
    {
        $quick = $this->makeRestaurant(['id' => 1, 'tags' => ['quick']]);
        $notQuick = $this->makeRestaurant(['id' => 2, 'tags' => ['group_friendly']]);
        $preference = $this->makePreference(['moodTags' => ['quick']]);

        $scoreQuick = $this->service->score($quick, $preference, 0);
        $scoreNotQuick = $this->service->score($notQuick, $preference, 0);

        $this->assertGreaterThan($scoreNotQuick, $scoreQuick);
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
