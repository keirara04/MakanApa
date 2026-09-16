<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use Database\Seeders\RestaurantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationSoloTest extends TestCase
{
    use RefreshDatabase;

    private const ORIGIN_LAT = 2.928400;
    private const ORIGIN_LNG = 101.780200;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'latitude' => self::ORIGIN_LAT,
            'longitude' => self::ORIGIN_LNG,
            'budgetMax' => 2,
            'maxDistanceKm' => 2.0,
            'moods' => ['comfort_food'],
        ], $overrides);
    }

    public function test_valid_request_returns_decision(): void
    {
        $this->seed(RestaurantSeeder::class);

        $response = $this->postJson('/api/v1/recommendations/solo', $this->validPayload());

        $response->assertOk()->assertJsonStructure([
            'decisionId',
            'algorithmVersion',
            'recommendation' => ['id', 'name', 'headline', 'latitude', 'longitude', 'distanceKm', 'rating', 'priceLevel', 'cuisines', 'openStatus'],
        ]);
    }

    public function test_malformed_coordinate_returns_422(): void
    {
        $response = $this->postJson('/api/v1/recommendations/solo', $this->validPayload(['latitude' => 999]));

        $response->assertStatus(422);
    }

    public function test_invalid_budget_returns_422(): void
    {
        $response = $this->postJson('/api/v1/recommendations/solo', $this->validPayload(['budgetMax' => 5]));

        $response->assertStatus(422);
    }

    public function test_invalid_distance_returns_422(): void
    {
        $response = $this->postJson('/api/v1/recommendations/solo', $this->validPayload(['maxDistanceKm' => -1]));

        $response->assertStatus(422);
    }

    public function test_no_candidates_handled_gracefully(): void
    {
        $this->seed(RestaurantSeeder::class);

        $response = $this->postJson('/api/v1/recommendations/solo', $this->validPayload([
            'latitude' => 1.0,
            'longitude' => 103.0,
            'maxDistanceKm' => 0.1,
        ]));

        $response->assertOk()->assertJson(['recommendation' => null]);
    }

    public function test_closed_restaurant_is_excluded(): void
    {
        $restaurant = Restaurant::create([
            'name' => 'Definitely Closed',
            'latitude' => self::ORIGIN_LAT,
            'longitude' => self::ORIGIN_LNG,
            'price_level' => 1,
            'rating' => 5.0,
            'is_active' => true,
            'opening_hours' => ['open_now' => false],
            'provider' => 'fixture',
        ]);

        $response = $this->postJson('/api/v1/recommendations/solo', $this->validPayload(['moods' => []]));

        $response->assertOk()->assertJson(['recommendation' => null]);
    }

    public function test_unknown_open_status_restaurant_is_included(): void
    {
        Restaurant::create([
            'name' => 'Unknown Hours Place',
            'latitude' => self::ORIGIN_LAT,
            'longitude' => self::ORIGIN_LNG,
            'price_level' => 1,
            'rating' => 5.0,
            'is_active' => true,
            'opening_hours' => null,
            'provider' => 'fixture',
        ]);

        $response = $this->postJson('/api/v1/recommendations/solo', $this->validPayload(['moods' => []]));

        $response->assertOk()->assertJsonPath('recommendation.name', 'Unknown Hours Place');
    }
}
