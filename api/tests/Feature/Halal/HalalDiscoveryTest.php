<?php

namespace Tests\Feature\Halal;

use App\Models\Decision;
use App\Models\DecisionRecommendation;
use App\Models\Restaurant;
use App\Services\Halal\HalalVerificationService;
use App\Services\RecommendationService;
use App\Support\Halal\HalalStatus;
use Database\Seeders\RestaurantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsHalalFixtures;
use Tests\TestCase;

class HalalDiscoveryTest extends TestCase
{
    use BuildsHalalFixtures, RefreshDatabase;

    private const BOX = ['north' => 2.940, 'south' => 2.918, 'east' => 101.800, 'west' => 101.770];

    private const SOLO = ['latitude' => 2.928400, 'longitude' => 101.780200, 'budgetMax' => 3, 'maxDistanceKm' => 5.0, 'moods' => []];

    private function markNonHalal(Restaurant $restaurant): void
    {
        app(HalalVerificationService::class)->recordAdminOverride($restaurant, HalalStatus::NonHalal, $this->makeAdmin(), 'Serves pork');
    }

    /** Everything seeded except one restaurant becomes non_halal; returns the survivor. */
    private function onlyOneHalalCandidate(): Restaurant
    {
        $this->seed(RestaurantSeeder::class);
        $all = Restaurant::orderBy('id')->get();
        $survivor = $all->first();
        $all->skip(1)->each(fn (Restaurant $r) => $this->markNonHalal($r));

        return $survivor;
    }

    public function test_solo_with_halal_param_never_returns_non_halal(): void
    {
        $survivor = $this->onlyOneHalalCandidate();

        $response = $this->postJson('/api/v1/recommendations/solo', self::SOLO + ['halal' => true])->assertOk();

        $this->assertSame($survivor->id, $response->json('recommendation.id'));
        $this->assertTrue((bool) Decision::find($response->json('decisionId'))->halal_only);
        $this->assertSame('unknown', $response->json('recommendation.halal.status'));
        $this->assertSame('help_verify', $response->json('recommendation.halal.display.action'));
    }

    public function test_solo_uses_stored_preference_when_no_param_sent(): void
    {
        $survivor = $this->onlyOneHalalCandidate();
        Sanctum::actingAs($this->makeUser(['halal_preference' => true]), ['*']);

        foreach (range(1, 5) as $_) {
            $this->assertSame($survivor->id, $this->postJson('/api/v1/recommendations/solo', self::SOLO)->json('recommendation.id'));
        }
    }

    public function test_explicit_param_false_overrides_stored_preference(): void
    {
        $this->onlyOneHalalCandidate();
        Sanctum::actingAs($this->makeUser(['halal_preference' => true]), ['*']);

        $seen = collect(range(1, 12))->map(fn () => $this->postJson('/api/v1/recommendations/solo', self::SOLO + ['halal' => false])->json('recommendation.id'))->unique();

        $this->assertGreaterThan(1, $seen->count());
    }

    public function test_map_markers_are_filtered_only_when_halal_on_and_unknown_is_kept(): void
    {
        $this->seed(RestaurantSeeder::class);
        $inBox = collect($this->getJson('/api/v1/places/nearby?'.http_build_query(self::BOX))->json('places'));
        $this->assertGreaterThan(1, $inBox->count());
        $victim = Restaurant::find($inBox->first()['id']);
        $this->markNonHalal($victim);

        $off = collect($this->getJson('/api/v1/places/nearby?'.http_build_query(self::BOX))->json('places'));
        $on = collect($this->getJson('/api/v1/places/nearby?'.http_build_query(self::BOX + ['halal' => 1]))->json('places'));

        $this->assertTrue($off->pluck('id')->contains($victim->id));
        $this->assertSame('Non-halal', $off->firstWhere('id', $victim->id)['halal']['display']['shortLabel']);
        $this->assertFalse($on->pluck('id')->contains($victim->id));
        $this->assertSame($off->count() - 1, $on->count());
        $this->assertSame('Not verified · Help verify', $on->first()['halal']['display']['shortLabel']);
    }

    public function test_reroll_excludes_non_halal_when_user_turned_filter_on_after_the_decision(): void
    {
        $this->seed(RestaurantSeeder::class);
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*']);
        $decision = $this->postJson('/api/v1/recommendations/solo', self::SOLO)->json();

        $user->update(['halal_preference' => true]);
        $nonHalalIds = Restaurant::orderBy('id')->get()->skip(1)->each(fn (Restaurant $r) => $this->markNonHalal($r))->pluck('id');

        foreach (range(1, 6) as $_) {
            $next = $this->postJson("/api/v1/decisions/{$decision['decisionId']}/reroll", [], ['X-Decision-Token' => $decision['clientToken']])
                ->assertOk()->json('recommendation.id');
            $this->assertNotContains($next, $nonHalalIds->all());
        }
    }

    public function test_reroll_excludes_a_place_that_became_non_halal_mid_session(): void
    {
        $this->seed(RestaurantSeeder::class);
        $decision = $this->postJson('/api/v1/recommendations/solo', self::SOLO + ['halal' => true])->json();
        $pool = DecisionRecommendation::where('decision_id', $decision['decisionId'])->pluck('restaurant_id');
        $this->assertGreaterThan(1, $pool->count());

        $flipped = Restaurant::whereIn('id', $pool)->where('id', '!=', $decision['recommendation']['id'])->get();
        $flipped->each(fn (Restaurant $r) => $this->markNonHalal($r));

        $next = $this->postJson("/api/v1/decisions/{$decision['decisionId']}/reroll", [], ['X-Decision-Token' => $decision['clientToken']])
            ->assertOk()->json('recommendation.id');
        $this->assertNotContains($next, $flipped->pluck('id')->all());
    }

    public function test_nearby_pick_respects_halal(): void
    {
        $this->seed(RestaurantSeeder::class);
        $visible = collect($this->getJson('/api/v1/places/nearby?'.http_build_query(self::BOX))->json('places'))->pluck('id');
        Restaurant::whereIn('id', $visible->skip(1))->get()->each(fn (Restaurant $r) => $this->markNonHalal($r));

        foreach (range(1, 4) as $_) {
            $id = $this->postJson('/api/v1/places/nearby/pick', [
                'viewport' => self::BOX, 'visiblePlaceIds' => $visible->all(),
                'latitude' => 2.928400, 'longitude' => 101.780200, 'halal' => true,
            ])->assertOk()->json('recommendation.id');
            $this->assertTrue($id === null || $id === $visible->first());
        }
    }

    public function test_profile_toggle_persists_and_is_returned(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*']);

        $this->patchJson('/api/v1/me/profile', ['halalPreference' => true])
            ->assertOk()->assertJsonPath('user.halalPreference', true);
        $this->assertTrue($user->fresh()->halal_preference);
        $this->getJson('/api/v1/auth/me')->assertJsonPath('user.halalPreference', true);
    }

    public function test_tie_breaker_orders_identical_candidates_but_only_when_filter_on(): void
    {
        $service = app(RecommendationService::class);
        $base = ['id' => 1, 'name' => 'A', 'latitude' => 2.9284, 'longitude' => 101.7802, 'rating' => 4.0, 'tags' => [], 'cuisines' => []];
        $pref = ['moodTags' => [], 'cuisines' => [], 'budgetMax' => null, 'maxDistanceKm' => 5.0, 'latitude' => 2.9284, 'longitude' => 101.7802];

        $certified = $service->scoreBreakdown($base + ['halal_status' => 'certified'], $pref + ['halalOnly' => true], 0.5);
        $unknown = $service->scoreBreakdown($base + ['halal_status' => 'unknown'], $pref + ['halalOnly' => true], 0.5);
        $off = $service->scoreBreakdown($base + ['halal_status' => 'certified'], $pref, 0.5);

        $this->assertGreaterThan($unknown['final'], $certified['final']);
        // Tie-breaker, not a signal: under 5 points on a 0-100 scale.
        $this->assertLessThan(5.0, $certified['final'] - $unknown['final']);
        $this->assertArrayNotHasKey('halalConfidence', $off['components']);
    }
}
