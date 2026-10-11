<?php

namespace Tests\Feature;

use App\Models\DecisionRecommendation;
use App\Models\TasteEvent;
use Database\Seeders\RestaurantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
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

        $response = $this->postJson(
            "/api/v1/decisions/{$decision['decisionId']}/accept",
            [],
            ['X-Decision-Token' => $decision['clientToken']]
        );

        $response->assertOk()->assertJson(['accepted' => true]);

        $shownRow = DecisionRecommendation::where('decision_id', $decision['decisionId'])
            ->where('restaurant_id', $decision['recommendation']['id'])
            ->first();

        $this->assertNotNull($shownRow->accepted_at);
    }

    public function test_accept_teaches_selera_even_with_makan_brain_off(): void
    {
        Config::set('brain.enabled', false);
        $this->seed(RestaurantSeeder::class);

        $decision = $this->postJson('/api/v1/recommendations/solo', [
            'latitude' => 2.928400,
            'longitude' => 101.780200,
            'budgetMax' => 3,
            'maxDistanceKm' => 5.0,
            'moods' => [],
            'installationId' => 'install-brain-off',
        ])->json();
        $this->assertSame('v1', $decision['algorithmVersion']);

        $this->postJson("/api/v1/decisions/{$decision['decisionId']}/accept", [], ['X-Decision-Token' => $decision['clientToken']])->assertOk();

        $this->assertTrue(TasteEvent::where('decision_id', $decision['decisionId'])->where('signal', 'accept')->exists());
    }
}
