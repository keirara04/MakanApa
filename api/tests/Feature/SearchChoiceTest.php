<?php

namespace Tests\Feature;

use App\Models\Decision;
use App\Models\DecisionRecommendation;
use App\Models\Restaurant;
use App\Models\TasteEvent;
use App\Models\TasteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SearchChoiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeRestaurant(array $overrides = []): Restaurant
    {
        return Restaurant::create(array_merge([
            'name' => 'KFC', 'latitude' => 2.9284, 'longitude' => 101.7802, 'is_active' => true,
            'provider' => 'google', 'provider_place_id' => 'place-'.uniqid(), 'food_category' => 'fast_food',
        ], $overrides));
    }

    private function choose(Restaurant $restaurant, string $choiceId): TestResponse
    {
        return $this->postJson("/api/v1/restaurants/{$restaurant->id}/choose", [
            'clientChoiceId' => $choiceId,
            'installationId' => 'install-1',
            'latitude' => 2.93, 'longitude' => 101.78,
            'search' => ['query' => 'kfc', 'radiusKm' => 6, 'source' => 'local'],
        ]);
    }

    public function test_choosing_a_search_result_records_one_accepted_search_decision(): void
    {
        $restaurant = $this->makeRestaurant();

        $response = $this->choose($restaurant, (string) Str::uuid())->assertCreated();

        $decision = Decision::sole();
        $this->assertSame($response->json('decisionId'), $decision->id);
        $this->assertSame($decision->client_token, $response->json('clientToken'));
        $this->assertSame('search', $decision->mode);
        $this->assertSame(['query' => 'kfc', 'radiusKm' => 6, 'source' => 'local'], $decision->search_context);
        $this->assertNotNull(DecisionRecommendation::sole()->accepted_at);
        $restaurant->refresh();
        $this->assertSame(1, $restaurant->accepted_count);
        $this->assertSame(1, $restaurant->impressions_count);
    }

    public function test_retrying_the_same_choice_is_idempotent(): void
    {
        $restaurant = $this->makeRestaurant();
        $choiceId = (string) Str::uuid();

        $first = $this->choose($restaurant, $choiceId)->assertCreated();
        $this->choose($restaurant, $choiceId)->assertOk()
            ->assertJsonPath('decisionId', $first->json('decisionId'))
            ->assertJsonPath('created', false);

        $this->assertSame(1, Decision::count());
        $this->assertSame(1, $restaurant->refresh()->accepted_count);
    }

    public function test_reusing_a_choice_id_for_another_place_is_rejected(): void
    {
        $choiceId = (string) Str::uuid();
        $this->choose($this->makeRestaurant(), $choiceId)->assertCreated();

        $this->choose($this->makeRestaurant(['name' => 'Other']), $choiceId)->assertStatus(409);
        $this->assertSame(1, Decision::count());
    }

    public function test_inactive_restaurant_cannot_be_chosen(): void
    {
        $this->choose($this->makeRestaurant(['is_active' => false]), (string) Str::uuid())->assertNotFound();
    }

    public function test_with_makan_brain_on_the_choice_teaches_selera_at_explicit_authority(): void
    {
        Config::set('brain.enabled', true);

        $this->choose($this->makeRestaurant(), (string) Str::uuid())->assertCreated();

        $event = TasteEvent::where('dimension', 'category')->sole();
        $this->assertSame('search_choose', $event->signal);
        $this->assertSame('explicit', $event->authority);
        $this->assertEqualsWithDelta((float) Config::get('brain.authority.explicit'), (float) $event->value, 0.0001);
        // Counts as "ate here" for Selera's recent-categories memory, like an accept.
        $this->assertSame('fast_food', TasteProfile::sole()->memory['recent'][0]['category'] ?? null);
    }

    public function test_with_makan_brain_off_no_taste_events_are_written(): void
    {
        Config::set('brain.enabled', false);

        $this->choose($this->makeRestaurant(), (string) Str::uuid())->assertCreated();

        $this->assertSame(0, TasteEvent::count());
    }
}
