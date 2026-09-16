<?php

namespace Tests\Feature;

use App\Models\Decision;
use App\Models\DecisionRecommendation;
use App\Models\Restaurant;
use Database\Seeders\RestaurantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NearbyPlacesTest extends TestCase
{
    use RefreshDatabase;

    /** A box loosely covering the Bangi fixture cluster (lat ~2.915-2.943, lon ~101.774-101.812). */
    private const BOX = [
        'north' => 2.940, 'south' => 2.918, 'east' => 101.800, 'west' => 101.770,
    ];

    public function test_index_returns_only_markers_inside_the_viewport(): void
    {
        $this->seed(RestaurantSeeder::class);

        $response = $this->getJson('/api/v1/places/nearby?'.http_build_query(self::BOX));

        $response->assertOk();
        $places = $response->json('places');

        $this->assertNotEmpty($places);
        foreach ($places as $place) {
            $this->assertLessThanOrEqual(self::BOX['north'], $place['latitude']);
            $this->assertGreaterThanOrEqual(self::BOX['south'], $place['latitude']);
            $this->assertLessThanOrEqual(self::BOX['east'], $place['longitude']);
            $this->assertGreaterThanOrEqual(self::BOX['west'], $place['longitude']);
        }

        // "Distant Lakeside Grill" (2.965, 101.812) and the inactive "Old Town Retro Diner" must
        // never appear — one is outside the box, the other is closed for good.
        $names = collect($places)->pluck('name');
        $this->assertFalse($names->contains('Distant Lakeside Grill'));
        $this->assertFalse($names->contains('Old Town Retro Diner (Closed Down)'));
    }

    public function test_index_applies_budget_filter(): void
    {
        $this->seed(RestaurantSeeder::class);

        $response = $this->getJson('/api/v1/places/nearby?'.http_build_query(self::BOX + ['budgetMax' => 1]));

        $response->assertOk();
        foreach ($response->json('places') as $place) {
            $this->assertNotNull($place['priceLevel']);
            $this->assertLessThanOrEqual(1, $place['priceLevel']);
        }
    }

    public function test_index_rejects_inverted_bounds(): void
    {
        $response = $this->getJson('/api/v1/places/nearby?'.http_build_query([
            'north' => 2.9, 'south' => 3.0, 'east' => 101.8, 'west' => 101.7,
        ]));

        $response->assertStatus(422);
    }

    public function test_pick_creates_a_decision_and_only_considers_visible_ids(): void
    {
        $this->seed(RestaurantSeeder::class);

        $visible = $this->getJson('/api/v1/places/nearby?'.http_build_query(self::BOX))->json('places');
        $visibleIds = collect($visible)->pluck('id')->all();

        $response = $this->postJson('/api/v1/places/nearby/pick', [
            'latitude' => 2.9284,
            'longitude' => 101.7802,
            'viewport' => self::BOX,
            'visiblePlaceIds' => $visibleIds,
        ]);

        $response->assertOk();
        $winnerId = $response->json('recommendation.id');
        $this->assertNotNull($winnerId);
        $this->assertContains($winnerId, $visibleIds);

        $decision = Decision::find($response->json('decisionId'));
        $this->assertSame('nearby', $decision->mode);
        $this->assertSame($winnerId, $decision->selected_restaurant_id);

        $shownRow = DecisionRecommendation::where('decision_id', $decision->id)
            ->whereNotNull('shown_at')
            ->first();
        $this->assertNotNull($shownRow);
        $this->assertSame($winnerId, $shownRow->restaurant_id);
    }

    public function test_pick_ignores_ids_the_client_claims_are_visible_but_are_actually_outside_the_viewport(): void
    {
        $this->seed(RestaurantSeeder::class);

        // "Distant Lakeside Grill" sits well outside BOX — a client can claim it's visible,
        // but the server must not trust that and must never pick it.
        $distant = Restaurant::where('name', 'Distant Lakeside Grill')->firstOrFail();
        $inBox = Restaurant::where('name', 'Nasi Ayam Bangi')->firstOrFail();

        $response = $this->postJson('/api/v1/places/nearby/pick', [
            'latitude' => 2.9284,
            'longitude' => 101.7802,
            'viewport' => self::BOX,
            'visiblePlaceIds' => [$distant->id, $inBox->id],
        ]);

        $response->assertOk();
        $this->assertNotEquals($distant->id, $response->json('recommendation.id'));
    }

    public function test_details_returns_restaurant_presentation(): void
    {
        $this->seed(RestaurantSeeder::class);
        $restaurant = Restaurant::where('name', 'Nasi Ayam Bangi')->firstOrFail();

        $response = $this->getJson("/api/v1/restaurants/{$restaurant->id}/details");

        $response->assertOk()->assertJson([
            'id' => $restaurant->id,
            'name' => 'Nasi Ayam Bangi',
            'rating' => 4.6,
            'priceLevel' => 1,
            // Fixture-provider restaurants have no Google photos/reviews to enrich with —
            // the endpoint still responds cleanly with empty arrays, not an error.
            'photos' => [],
            'reviews' => [],
        ]);
    }

    public function test_details_404s_for_nonexistent_restaurant(): void
    {
        $this->getJson('/api/v1/restaurants/999999/details')->assertStatus(404);
    }

    public function test_pick_returns_no_recommendation_when_visible_ids_resolve_to_nothing(): void
    {
        $this->seed(RestaurantSeeder::class);

        $response = $this->postJson('/api/v1/places/nearby/pick', [
            'latitude' => 2.9284,
            'longitude' => 101.7802,
            'viewport' => self::BOX,
            'visiblePlaceIds' => [999999],
        ]);

        $response->assertOk()->assertJson(['recommendation' => null]);
        $this->assertNotNull($response->json('decisionId'));

        $decision = Decision::find($response->json('decisionId'));
        $this->assertNull($decision->selected_restaurant_id);
    }
}
