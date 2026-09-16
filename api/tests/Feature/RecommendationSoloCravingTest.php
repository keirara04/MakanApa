<?php

namespace Tests\Feature;

use App\Models\DecisionRecommendation;
use App\Services\Craving\CravingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * End-to-end coverage of the "ice cream" bug and its fix: a burger place returned by Nearby
 * Search must not beat an ice cream shop only Text Search finds, whether the craving resolves
 * via taxonomy or AI, and the endpoint must degrade gracefully when AI is unavailable.
 */
class RecommendationSoloCravingTest extends TestCase
{
    use RefreshDatabase;

    private const LAT = 2.928400;

    private const LNG = 101.780200;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.places.provider' => 'google', 'services.places.google_api_key' => 'fake-key']);
    }

    private function googlePlace(string $id, string $name, array $types): array
    {
        return [
            'id' => $id,
            'displayName' => ['text' => $name],
            'location' => ['latitude' => self::LAT, 'longitude' => self::LNG],
            'types' => $types,
            'rating' => 4.5,
            'priceLevel' => 'PRICE_LEVEL_MODERATE',
            'currentOpeningHours' => ['openNow' => true],
        ];
    }

    private function fakeGoogleWithBurgerAndIceCream(): void
    {
        Http::fake([
            '*searchNearby*' => Http::response(['places' => [
                $this->googlePlace('burger-1', 'Best Burger In Town', ['hamburger_restaurant']),
            ]]),
            '*searchText*' => Http::response(['places' => [
                $this->googlePlace('icecream-1', 'Inside Scoop', ['ice_cream_shop']),
            ]]),
            'openrouter.ai/*' => Http::response(['choices' => [
                ['message' => ['content' => json_encode(['category' => 'dessert', 'subcategory' => 'ice_cream', 'confidence' => 0.91])]],
            ]]),
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'latitude' => self::LAT,
            'longitude' => self::LNG,
            'budgetMax' => 3,
            'maxDistanceKm' => 2.0,
            'moods' => [],
        ], $overrides);
    }

    /**
     * The endpoint's `recommendation` is a weighted-random pick among the top 5 ranked
     * candidates (see RecommendationService::pick()) — not deterministically "the highest
     * score" — so asserting on it directly would be flaky by the app's own design. What must
     * be deterministic is the *ranking* that scoring produced, which is exactly what gets
     * persisted as rank 1 in decision_recommendations regardless of which candidate the
     * weighted roll happens to land on.
     */
    private function assertTopRankedCandidateIs(string $expectedName): void
    {
        $topRanked = DecisionRecommendation::where('rank', 1)->latest('id')->first();

        $this->assertNotNull($topRanked);
        $this->assertSame($expectedName, $topRanked->restaurant->name);
    }

    public function test_taxonomy_resolved_craving_picks_ice_cream_shop_over_burger(): void
    {
        $this->fakeGoogleWithBurgerAndIceCream();

        $response = $this->postJson('/api/v1/recommendations/solo', $this->validPayload(['craving' => 'ice cream']));

        $response->assertOk();
        $this->assertTopRankedCandidateIs('Inside Scoop');
        $response->assertJsonPath('craving.matched', true);
        $response->assertJsonPath('craving.resolvedAs', 'ice_cream');
        $response->assertJsonPath('craving.source', 'taxonomy');
    }

    public function test_ai_resolved_craving_picks_ice_cream_shop_over_burger(): void
    {
        config(['services.openrouter.api_key' => 'fake-openrouter-key']);
        $this->app->forgetInstance(CravingResolver::class);
        $this->fakeGoogleWithBurgerAndIceCream();

        $response = $this->postJson('/api/v1/recommendations/solo', $this->validPayload([
            'craving' => 'nak benda manis sejuk',
        ]));

        $response->assertOk();
        $this->assertTopRankedCandidateIs('Inside Scoop');
        $response->assertJsonPath('craving.matched', true);
        $response->assertJsonPath('craving.resolvedAs', 'ice_cream');
        $response->assertJsonPath('craving.source', 'ai');
    }

    public function test_ai_failure_degrades_gracefully_to_normal_recommendations(): void
    {
        config(['services.openrouter.api_key' => 'fake-openrouter-key']);
        $this->app->forgetInstance(CravingResolver::class);

        Http::fake([
            '*searchNearby*' => Http::response(['places' => [
                $this->googlePlace('burger-1', 'Best Burger In Town', ['hamburger_restaurant']),
            ]]),
            '*searchText*' => Http::response(['places' => []]),
            'openrouter.ai/*' => fn () => throw new ConnectionException('timed out'),
        ]);

        $response = $this->postJson('/api/v1/recommendations/solo', $this->validPayload([
            'craving' => 'nak benda manis sejuk',
        ]));

        $response->assertOk();
        $response->assertJsonPath('recommendation.name', 'Best Burger In Town');
        $response->assertJsonPath('craving.matched', false);
        $response->assertJsonPath('craving.source', 'none');
    }

    public function test_moods_and_craving_together_still_returns_422(): void
    {
        $response = $this->postJson('/api/v1/recommendations/solo', $this->validPayload([
            'moods' => ['nasi_kandar'],
            'craving' => 'ice cream',
        ]));

        $response->assertStatus(422);
    }
}
