<?php

namespace Tests\Feature;

use App\Models\Decision;
use App\Models\Restaurant;
use Database\Seeders\RestaurantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SavedPickTest extends TestCase
{
    use RefreshDatabase;

    /** Inside the Bangi fixture cluster — "Distant Lakeside Grill" sits ~4 km north of here. */
    private const ORIGIN = ['latitude' => 2.9284, 'longitude' => 101.7802];

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->seed(RestaurantSeeder::class);
    }

    private function pickSaved(array $savedPlaceIds, array $overrides = [])
    {
        return $this->postJson('/api/v1/recommendations/saved', [
            ...self::ORIGIN,
            'savedPlaceIds' => $savedPlaceIds,
            ...$overrides,
        ]);
    }

    public function test_picks_a_saved_place_far_outside_any_viewport_and_records_a_saved_decision(): void
    {
        $distant = Restaurant::where('name', 'Distant Lakeside Grill')->firstOrFail();

        $response = $this->pickSaved([$distant->id])->assertOk();

        $this->assertSame($distant->id, $response->json('recommendation.id'));
        $this->assertSame('v1', $response->json('algorithmVersion'));
        $this->assertSame('saved', Decision::findOrFail($response->json('decisionId'))->mode);
    }

    public function test_only_ever_picks_from_the_saved_ids(): void
    {
        $saved = Restaurant::where('is_active', true)->orderBy('id')->limit(2)->pluck('id')->all();

        foreach (range(1, 5) as $attempt) {
            $this->assertContains($this->pickSaved($saved)->assertOk()->json('recommendation.id'), $saved);
        }
    }

    public function test_inactive_and_unknown_ids_never_win(): void
    {
        $inactive = Restaurant::where('name', 'Old Town Retro Diner (Closed Down)')->firstOrFail();

        $this->pickSaved([$inactive->id, 999_999])->assertOk()->assertJsonPath('recommendation', null);
    }

    public function test_a_merged_away_id_resolves_to_the_surviving_restaurant(): void
    {
        [$survivor, $mergedAway] = Restaurant::where('is_active', true)->orderBy('id')->limit(2)->get()->all();
        $mergedAway->update(['merged_into_restaurant_id' => $survivor->id]);

        $this->pickSaved([$mergedAway->id])->assertOk()->assertJsonPath('recommendation.id', $survivor->id);
    }

    public function test_budget_filter_drops_saved_places_over_budget(): void
    {
        $cheap = Restaurant::where('is_active', true)->where('price_level', 1)->firstOrFail();
        $pricey = Restaurant::where('is_active', true)->where('price_level', '>=', 2)->firstOrFail();

        $this->pickSaved([$cheap->id, $pricey->id], ['budgetMax' => 1])
            ->assertOk()
            ->assertJsonPath('recommendation.id', $cheap->id);
    }

    public function test_rejects_more_than_the_maximum_number_of_saved_ids(): void
    {
        $this->pickSaved(range(1, 101))->assertUnprocessable()->assertJsonValidationErrors('savedPlaceIds');
    }

    public function test_brain_pick_leads_with_the_saved_places_reason(): void
    {
        Config::set('brain.enabled', true);
        $saved = Restaurant::where('is_active', true)->orderBy('id')->limit(3)->pluck('id')->all();

        $response = $this->pickSaved($saved, ['installationId' => 'install-1'])->assertOk();

        $this->assertSame('v2', $response->json('algorithmVersion'));
        $this->assertContains($response->json('recommendation.id'), $saved);
        $this->assertSame('One of your saved places', $response->json('recommendation.reasons.0.text'));
        $this->assertSame('saved', Decision::findOrFail($response->json('decisionId'))->mode);
    }
}
