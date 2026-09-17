<?php

namespace Tests\Feature;

use App\Services\Craving\CravingResolver;
use App\Services\PlacesService;
use App\Support\DiscoveryMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers the audit finding: dropping searchText()'s hardcoded includedType shouldn't mean
 * craving search goes fully unrestricted — it should use the resolved concept's own
 * FoodTaxonomy placeType when one exists, and 'restaurant' otherwise (unchanged prior behavior).
 */
class CravingTextSearchTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.places.provider' => 'google', 'services.places.google_api_key' => 'fake-key']);
    }

    public function test_craving_with_a_specific_place_type_uses_it_for_text_search(): void
    {
        Http::fake([
            '*searchNearby*' => Http::response(['places' => []]),
            '*searchText*' => Http::response(['places' => []]),
        ]);

        $craving = (new CravingResolver)->resolve('ice cream');
        app(PlacesService::class)->nearbyRestaurants(2.9284, 101.7802, 2.0, $craving);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'searchText')
            && $request['includedType'] === 'ice_cream_shop');
    }

    public function test_dish_craving_without_a_place_type_falls_back_to_restaurant(): void
    {
        Http::fake([
            '*searchNearby*' => Http::response(['places' => []]),
            '*searchText*' => Http::response(['places' => []]),
        ]);

        $craving = (new CravingResolver)->resolve('nasi kandar');
        app(PlacesService::class)->nearbyRestaurants(2.9284, 101.7802, 2.0, $craving);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'searchText')
            && $request['includedType'] === 'restaurant');
    }

    public function test_unresolved_raw_text_craving_falls_back_to_restaurant(): void
    {
        Http::fake([
            '*searchNearby*' => Http::response(['places' => []]),
            '*searchText*' => Http::response(['places' => []]),
        ]);

        $craving = (new CravingResolver)->resolve('roti john cheese banjir');
        app(PlacesService::class)->nearbyRestaurants(2.9284, 101.7802, 2.0, $craving);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'searchText')
            && $request['includedType'] === 'restaurant');
    }

    public function test_discovery_lane_text_search_is_unrestricted(): void
    {
        Http::fake([
            '*searchNearby*' => Http::response(['places' => []]),
            '*searchText*' => Http::response(['places' => []]),
        ]);

        app(PlacesService::class)->nearbyRestaurants(
            2.9284, 101.7802, 2.0, craving: null,
            mode: DiscoveryMode::LowKey
        );

        Http::assertSent(fn ($request) => str_contains($request->url(), 'searchText')
            && ! array_key_exists('includedType', $request->data()));
    }
}
