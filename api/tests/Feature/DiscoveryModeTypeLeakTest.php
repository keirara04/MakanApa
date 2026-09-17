<?php

namespace Tests\Feature;

use App\Models\PlaceSyncArea;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers the audit finding: a Cafe/Low-key sync must not leak cafes/coffee_shops/bakeries into
 * a later Normal-mode read of the same (still-cached) area.
 */
class DiscoveryModeTypeLeakTest extends TestCase
{
    use RefreshDatabase;

    private const BOX = [
        'north' => 2.940, 'south' => 2.918, 'east' => 101.800, 'west' => 101.770,
    ];

    private function place(string $id, string $name, array $types): array
    {
        return [
            'id' => $id,
            'displayName' => ['text' => $name],
            'location' => ['latitude' => 2.928, 'longitude' => 101.780],
            'types' => $types,
            'rating' => 4.5,
            'userRatingCount' => 42,
        ];
    }

    public function test_cafe_synced_cafes_and_bakeries_do_not_leak_into_normal_mode(): void
    {
        config(['services.places.provider' => 'google', 'services.places.google_api_key' => 'fake-key']);

        Http::fake([
            '*searchNearby*' => Http::response(['places' => [
                $this->place('r1', 'Nasi Kandar Deluxe', ['restaurant']),
                $this->place('c1', 'Quiet Corner Cafe', ['cafe']),
                $this->place('b1', 'Warm Bread Bakery', ['bakery']),
            ]]),
            '*searchText*' => Http::response(['places' => []]),
        ]);

        // Sync the area under Cafe mode — all three (restaurant + cafe + bakery) get upserted.
        $this->getJson('/api/v1/places/nearby?'.http_build_query(self::BOX + ['mode' => 'cafe']))->assertOk();

        // Same area, still within the cache window, Normal mode this time.
        $normal = $this->getJson('/api/v1/places/nearby?'.http_build_query(self::BOX))->assertOk();
        $names = collect($normal->json('places'))->pluck('name');

        $this->assertTrue($names->contains('Nasi Kandar Deluxe'));
        $this->assertFalse($names->contains('Quiet Corner Cafe'));
        $this->assertFalse($names->contains('Warm Bread Bakery'));
    }

    public function test_cafe_mode_still_sees_everything_including_the_cafes_it_synced(): void
    {
        config(['services.places.provider' => 'google', 'services.places.google_api_key' => 'fake-key']);

        Http::fake([
            '*searchNearby*' => Http::response(['places' => [
                $this->place('r1', 'Nasi Kandar Deluxe', ['restaurant']),
                $this->place('c1', 'Quiet Corner Cafe', ['cafe']),
            ]]),
            '*searchText*' => Http::response(['places' => []]),
        ]);

        $response = $this->getJson('/api/v1/places/nearby?'.http_build_query(self::BOX + ['mode' => 'cafe']))->assertOk();
        $names = collect($response->json('places'))->pluck('name');

        $this->assertTrue($names->contains('Nasi Kandar Deluxe'));
        $this->assertTrue($names->contains('Quiet Corner Cafe'));
    }

    public function test_a_specific_restaurant_subtype_is_not_mistaken_for_cafe_leaning(): void
    {
        // Regression guard for the fix's own failure mode: a hamburger_restaurant/ice_cream_shop
        // etc. must stay visible in Normal mode even though its own types[] never literally
        // contains the string "restaurant" — only literal cafe/coffee_shop/bakery should exclude.
        config(['services.places.provider' => 'google', 'services.places.google_api_key' => 'fake-key']);

        Http::fake([
            '*searchNearby*' => Http::response(['places' => [
                $this->place('h1', 'Best Burger In Town', ['hamburger_restaurant']),
            ]]),
            '*searchText*' => Http::response(['places' => []]),
        ]);

        $response = $this->getJson('/api/v1/places/nearby?'.http_build_query(self::BOX))->assertOk();
        $names = collect($response->json('places'))->pluck('name');

        $this->assertTrue($names->contains('Best Burger In Town'));
    }

    public function test_legacy_restaurant_with_no_recorded_google_types_stays_visible(): void
    {
        config(['services.places.provider' => 'google', 'services.places.google_api_key' => 'fake-key']);

        Restaurant::create([
            'name' => 'Old Sync Restaurant',
            'latitude' => 2.928,
            'longitude' => 101.780,
            'is_active' => true,
            'provider' => 'google',
            'provider_place_id' => 'legacy-1',
            'google_types' => null,
        ]);
        PlaceSyncArea::create([
            'provider' => 'google',
            'latitude' => 2.928,
            'longitude' => 101.780,
            'radius_km' => 5,
            'synced_at' => now(),
            'types' => ['restaurant'],
        ]);

        Http::fake(); // area already covered — no Google call should even happen

        $response = $this->getJson('/api/v1/places/nearby?'.http_build_query(self::BOX))->assertOk();
        $names = collect($response->json('places'))->pluck('name');

        $this->assertTrue($names->contains('Old Sync Restaurant'));
    }
}
