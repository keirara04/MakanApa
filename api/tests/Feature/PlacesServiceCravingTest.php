<?php

namespace Tests\Feature;

use App\Services\Craving\CravingResolver;
use App\Services\PlacesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlacesServiceCravingTest extends TestCase
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

    public function test_text_search_only_hit_appears_in_final_candidate_list(): void
    {
        Http::fake([
            '*searchNearby*' => Http::response(['places' => [
                $this->googlePlace('nearby-1', 'Best Burger In Town', ['hamburger_restaurant']),
            ]]),
            '*searchText*' => Http::response(['places' => [
                $this->googlePlace('textsearch-1', 'Inside Scoop', ['ice_cream_shop']),
            ]]),
        ]);

        $craving = (new CravingResolver)->resolve('ice cream');
        $restaurants = app(PlacesService::class)->nearbyRestaurants(self::LAT, self::LNG, 2.0, $craving);

        $names = array_column($restaurants, 'name');
        $this->assertContains('Inside Scoop', $names);
        $this->assertContains('Best Burger In Town', $names);
    }

    public function test_place_returned_by_both_searches_is_not_duplicated(): void
    {
        Http::fake([
            '*searchNearby*' => Http::response(['places' => [
                $this->googlePlace('same-place', 'Inside Scoop', ['ice_cream_shop']),
            ]]),
            '*searchText*' => Http::response(['places' => [
                $this->googlePlace('same-place', 'Inside Scoop', ['ice_cream_shop']),
            ]]),
        ]);

        $craving = (new CravingResolver)->resolve('ice cream');
        $restaurants = app(PlacesService::class)->nearbyRestaurants(self::LAT, self::LNG, 2.0, $craving);

        $matching = array_filter($restaurants, fn ($r) => $r['name'] === 'Inside Scoop');
        $this->assertCount(1, $matching);
    }

    public function test_at_most_one_text_search_call_regardless_of_search_term_count(): void
    {
        Http::fake([
            '*searchNearby*' => Http::response(['places' => []]),
            '*searchText*' => Http::response(['places' => []]),
        ]);

        $craving = (new CravingResolver)->resolve('ice cream'); // ice_cream has multiple aliases
        app(PlacesService::class)->nearbyRestaurants(self::LAT, self::LNG, 2.0, $craving);

        // radius 2.0 is above TILE_RADIUS_THRESHOLD_KM, so the nearby sync itself is 7 tiled
        // calls; text search (never tiled) adds exactly one more regardless of alias count.
        Http::assertSentCount(8);
    }

    public function test_second_request_in_same_area_within_cache_window_skips_text_search_call(): void
    {
        Http::fake([
            '*searchNearby*' => Http::response(['places' => []]),
            '*searchText*' => Http::response(['places' => [
                $this->googlePlace('textsearch-1', 'Inside Scoop', ['ice_cream_shop']),
            ]]),
        ]);

        $craving = (new CravingResolver)->resolve('ice cream');
        $service = app(PlacesService::class);
        $service->nearbyRestaurants(self::LAT, self::LNG, 2.0, $craving);
        $service->nearbyRestaurants(self::LAT, self::LNG, 2.0, $craving);

        // First request: 7 tiled nearby calls + 1 text search. Second request: every tile is
        // covered by the area-sync cache and text search is skipped by its own result cache —
        // neither Google endpoint is hit again.
        Http::assertSentCount(8);
    }

    public function test_no_craving_never_triggers_text_search(): void
    {
        Http::fake(['*searchNearby*' => Http::response(['places' => []])]);

        app(PlacesService::class)->nearbyRestaurants(self::LAT, self::LNG, 2.0, null);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'searchText'));
    }

    public function test_text_search_failure_still_returns_nearby_results_instead_of_throwing(): void
    {
        Http::fake([
            '*searchNearby*' => Http::response(['places' => [
                $this->googlePlace('nearby-1', 'Best Burger In Town', ['hamburger_restaurant']),
            ]]),
            '*searchText*' => Http::response(['error' => 'rate limited'], 429),
        ]);

        $craving = (new CravingResolver)->resolve('ice cream');
        $restaurants = app(PlacesService::class)->nearbyRestaurants(self::LAT, self::LNG, 2.0, $craving);

        $names = array_column($restaurants, 'name');
        $this->assertContains('Best Burger In Town', $names);
    }
}
