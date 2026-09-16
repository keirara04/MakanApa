<?php

namespace Tests\Feature;

use App\Models\DecisionRecommendation;
use Database\Seeders\RestaurantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcceptDecisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_accept_records_accepted_at_on_currently_shown_row(): void
    {
        $this->seed(RestaurantSeeder::class);

        $decision = $this->postJson('/api/v1/recommendations/solo', [
            'latitude' => 2.928400,
            'longitude' => 101.780200,
            'budgetMax' => 3,
            'maxDistanceKm' => 5.0,
            'moods' => [],
        ])->json();

        $response = $this->postJson("/api/v1/decisions/{$decision['decisionId']}/accept");

        $response->assertOk()->assertJson(['accepted' => true]);

        $shownRow = DecisionRecommendation::where('decision_id', $decision['decisionId'])
            ->where('restaurant_id', $decision['recommendation']['id'])
            ->first();

        $this->assertNotNull($shownRow->accepted_at);
    }
}
