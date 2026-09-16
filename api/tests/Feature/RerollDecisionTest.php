<?php

namespace Tests\Feature;

use App\Models\DecisionRecommendation;
use Database\Seeders\RestaurantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RerollDecisionTest extends TestCase
{
    use RefreshDatabase;

    private function createDecision(): array
    {
        $this->seed(RestaurantSeeder::class);

        $response = $this->postJson('/api/v1/recommendations/solo', [
            'latitude' => 2.928400,
            'longitude' => 101.780200,
            'budgetMax' => 3,
            'maxDistanceKm' => 5.0,
            'moods' => [],
        ]);

        return $response->json();
    }

    public function test_reroll_never_immediately_repeats(): void
    {
        $decision = $this->createDecision();
        $previousId = $decision['recommendation']['id'];

        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson("/api/v1/decisions/{$decision['decisionId']}/reroll");
            $response->assertOk();
            $nextId = $response->json('recommendation.id');

            if ($nextId !== null) {
                $this->assertNotEquals($previousId, $nextId);
                $previousId = $nextId;
            }
        }
    }

    public function test_reroll_marks_previous_rejected_and_next_shown(): void
    {
        $decision = $this->createDecision();
        $originalRestaurantId = $decision['recommendation']['id'];

        $this->postJson("/api/v1/decisions/{$decision['decisionId']}/reroll")->assertOk();

        $originalRow = DecisionRecommendation::where('decision_id', $decision['decisionId'])
            ->where('restaurant_id', $originalRestaurantId)
            ->first();

        $this->assertNotNull($originalRow->rejected_at);

        $shownRow = DecisionRecommendation::where('decision_id', $decision['decisionId'])
            ->whereNotNull('shown_at')
            ->whereNull('rejected_at')
            ->first();

        $this->assertNotNull($shownRow);
    }

    public function test_reroll_404s_on_nonexistent_decision(): void
    {
        $response = $this->postJson('/api/v1/decisions/999999/reroll');

        $response->assertStatus(404);
    }
}
