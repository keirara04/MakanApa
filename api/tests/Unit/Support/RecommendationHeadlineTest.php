<?php

namespace Tests\Unit\Support;

use App\Support\RecommendationHeadline;
use PHPUnit\Framework\TestCase;

class RecommendationHeadlineTest extends TestCase
{
    public function test_prefers_signature_dish_over_everything(): void
    {
        $restaurant = [
            'signature_dish' => 'Nasi Kandar',
            'food_category' => 'burger',
            'cuisines' => ['malay'],
        ];

        $this->assertSame('NASI KANDAR.', RecommendationHeadline::for($restaurant));
    }

    public function test_uses_food_category_when_cuisines_are_empty(): void
    {
        // Exactly the BGM Burgerman bug: Google's hamburger_restaurant/fast_food_restaurant
        // types don't map to any nationality cuisine, so cuisines comes back empty.
        $restaurant = [
            'signature_dish' => null,
            'food_category' => 'burger',
            'cuisines' => [],
        ];

        $this->assertSame('BURGERS.', RecommendationHeadline::for($restaurant));
    }

    public function test_food_category_wins_over_cuisine_when_both_present(): void
    {
        $restaurant = [
            'signature_dish' => null,
            'food_category' => 'ramen',
            'cuisines' => ['japanese'],
        ];

        $this->assertSame('RAMEN.', RecommendationHeadline::for($restaurant));
    }

    public function test_falls_back_to_cuisine_when_no_food_category(): void
    {
        $restaurant = [
            'signature_dish' => null,
            'food_category' => null,
            'cuisines' => ['korean'],
        ];

        $this->assertSame('KOREAN.', RecommendationHeadline::for($restaurant));
    }

    public function test_falls_back_to_makan_when_nothing_matches(): void
    {
        $restaurant = [
            'signature_dish' => null,
            'food_category' => null,
            'cuisines' => [],
        ];

        $this->assertSame('MAKAN.', RecommendationHeadline::for($restaurant));
    }

    public function test_unrecognized_food_category_falls_through_to_cuisine(): void
    {
        $restaurant = [
            'signature_dish' => null,
            'food_category' => 'some_new_google_type_not_yet_mapped',
            'cuisines' => ['thai'],
        ];

        $this->assertSame('THAI.', RecommendationHeadline::for($restaurant));
    }
}
