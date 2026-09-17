<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantSave;
use Database\Seeders\RestaurantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CommunitySignalTest extends TestCase
{
    use RefreshDatabase;

    private function decide(array $overrides = []): array
    {
        return $this->postJson('/api/v1/recommendations/solo', array_merge([
            'latitude' => 2.928400,
            'longitude' => 101.780200,
            'budgetMax' => 3,
            'maxDistanceKm' => 5.0,
            'moods' => [],
        ], $overrides))->json();
    }

    public function test_accept_is_idempotent_against_retries(): void
    {
        $this->seed(RestaurantSeeder::class);
        $decision = $this->decide();
        $restaurantId = $decision['recommendation']['id'];

        // Simulates a client retrying after a lost response — the second call must not
        // double-count, since accepted_at is already set from the first.
        $this->postJson("/api/v1/decisions/{$decision['decisionId']}/accept", [], ['X-Decision-Token' => $decision['clientToken']]);
        $this->postJson("/api/v1/decisions/{$decision['decisionId']}/accept", [], ['X-Decision-Token' => $decision['clientToken']]);

        $this->assertSame(1, Restaurant::find($restaurantId)->accepted_count);
    }

    public function test_save_is_idempotent(): void
    {
        $this->seed(RestaurantSeeder::class);
        $restaurant = Restaurant::first();

        $this->postJson("/api/v1/restaurants/{$restaurant->id}/save", ['installationId' => 'device-abc'])->assertOk();
        $this->postJson("/api/v1/restaurants/{$restaurant->id}/save", ['installationId' => 'device-abc'])->assertOk();

        $this->assertSame(1, RestaurantSave::where('restaurant_id', $restaurant->id)->count());
    }

    public function test_unsave_removes_the_row(): void
    {
        $this->seed(RestaurantSeeder::class);
        $restaurant = Restaurant::first();

        $this->postJson("/api/v1/restaurants/{$restaurant->id}/save", ['installationId' => 'device-abc']);
        $this->postJson("/api/v1/restaurants/{$restaurant->id}/unsave", ['installationId' => 'device-abc'])->assertOk();

        $this->assertSame(0, RestaurantSave::where('restaurant_id', $restaurant->id)->count());
    }

    public function test_rebuild_signal_counts_command_reproduces_counts_from_events(): void
    {
        $this->seed(RestaurantSeeder::class);
        $decision = $this->decide();
        $restaurantId = $decision['recommendation']['id'];
        $this->postJson("/api/v1/decisions/{$decision['decisionId']}/accept", [], ['X-Decision-Token' => $decision['clientToken']]);

        // Simulate drift, then rebuild from decision_recommendations (canonical) and confirm
        // it lands back on the correct values.
        Restaurant::whereKey($restaurantId)->update(['impressions_count' => 999, 'accepted_count' => 999]);

        Artisan::call('restaurants:rebuild-signal-counts');

        $restaurant = Restaurant::find($restaurantId);
        $this->assertSame(1, $restaurant->impressions_count);
        $this->assertSame(1, $restaurant->accepted_count);
    }

    public function test_vibe_tag_requires_a_valid_decision_token(): void
    {
        $this->seed(RestaurantSeeder::class);
        $decision = $this->decide();

        $this->postJson(
            "/api/v1/decisions/{$decision['decisionId']}/vibe-tag",
            ['vibe' => 'study'],
            ['X-Decision-Token' => 'wrong-token']
        )->assertStatus(403);
    }

    public function test_vibe_tag_records_a_vote_for_the_shown_restaurant(): void
    {
        $this->seed(RestaurantSeeder::class);
        $decision = $this->decide();

        $this->postJson(
            "/api/v1/decisions/{$decision['decisionId']}/vibe-tag",
            ['vibe' => 'study'],
            ['X-Decision-Token' => $decision['clientToken']]
        )->assertOk()->assertJson(['tagged' => true]);

        $this->assertDatabaseHas('restaurant_vibe_votes', [
            'restaurant_id' => $decision['recommendation']['id'],
            'vibe' => 'study',
        ]);
    }

    public function test_personal_fit_is_absent_below_the_minimum_accept_history(): void
    {
        config(['recommendation.debug' => true]);
        $this->seed(RestaurantSeeder::class);
        $installationId = 'device-personal-fit';

        // Only 2 accepted decisions — below personal_fit_min_accepts (3).
        for ($i = 0; $i < 2; $i++) {
            $decision = $this->decide(['installationId' => $installationId]);
            $this->postJson("/api/v1/decisions/{$decision['decisionId']}/accept", [], ['X-Decision-Token' => $decision['clientToken']]);
        }

        $response = $this->decide(['installationId' => $installationId]);
        $this->assertArrayNotHasKey('personalFit', $response['debug']['pickScoreBreakdown']);
    }

    public function test_personal_fit_appears_once_enough_accept_history_exists(): void
    {
        config(['recommendation.debug' => true, 'recommendation.personal_fit_min_accepts' => 1]);
        $this->seed(RestaurantSeeder::class);
        $installationId = 'device-personal-fit-2';

        $first = $this->decide(['installationId' => $installationId]);
        $this->postJson("/api/v1/decisions/{$first['decisionId']}/accept", [], ['X-Decision-Token' => $first['clientToken']]);

        $response = $this->decide(['installationId' => $installationId]);
        $this->assertArrayHasKey('personalFit', $response['debug']['pickScoreBreakdown']);
    }
}
