<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiscoveryModeGooglePlacesTest extends TestCase
{
    use RefreshDatabase;

    private const BOX = [
        'north' => 2.940, 'south' => 2.918, 'east' => 101.800, 'west' => 101.770,
    ];

    private function fakeGooglePlacesResponse(): array
    {
        return [
            'places' => [
                [
                    'id' => 'place-1',
                    'displayName' => ['text' => 'Some Cafe'],
                    'location' => ['latitude' => 2.928, 'longitude' => 101.780],
                    'types' => ['cafe'],
                    'rating' => 4.5,
                    'userRatingCount' => 42,
                ],
            ],
        ];
    }

    private function useGoogleProvider(): void
    {
        config([
            'services.places.provider' => 'google',
            'services.places.google_api_key' => 'test-key',
        ]);
    }

    public function test_cafe_mode_requests_cafe_leaning_included_types(): void
    {
        $this->useGoogleProvider();
        Http::fake(['places.googleapis.com/v1/places:searchNearby' => Http::response($this->fakeGooglePlacesResponse())]);

        $this->getJson('/api/v1/places/nearby?'.http_build_query(self::BOX + ['mode' => 'cafe']))->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'searchNearby')
                && $request['includedTypes'] === ['restaurant', 'meal_takeaway', 'meal_delivery', 'food_court', 'cafe', 'coffee_shop', 'bakery'];
        });
    }

    public function test_normal_mode_requests_the_base_food_place_types(): void
    {
        $this->useGoogleProvider();
        Http::fake(['places.googleapis.com/v1/places:searchNearby' => Http::response($this->fakeGooglePlacesResponse())]);

        $this->getJson('/api/v1/places/nearby?'.http_build_query(self::BOX))->assertOk();

        Http::assertSent(fn ($request) => $request['includedTypes'] === ['restaurant', 'meal_takeaway', 'meal_delivery', 'food_court']);
    }

    public function test_normal_mode_sync_does_not_satisfy_a_later_cafe_mode_request(): void
    {
        $this->useGoogleProvider();
        Http::fake([
            'places.googleapis.com/v1/places:searchNearby' => Http::response($this->fakeGooglePlacesResponse()),
            'places.googleapis.com/v1/places:searchText' => Http::response($this->fakeGooglePlacesResponse()),
        ]);

        // First call, Normal mode — syncs and caches the area for ['restaurant'] only.
        $this->getJson('/api/v1/places/nearby?'.http_build_query(self::BOX))->assertOk();

        // Second call, Cafe mode, same area, well within the cache window — must NOT be treated
        // as "covered" by the Normal-mode sync, since it needs cafe/coffee_shop/bakery too.
        $this->getJson('/api/v1/places/nearby?'.http_build_query(self::BOX + ['mode' => 'cafe']))->assertOk();

        // BOX's radius (~2.07km) is above TILE_RADIUS_THRESHOLD_KM, so each mode's sync is 7
        // tiles, not 1 — 2 independent syncs (Normal, then Cafe) = 14 total requests.
        $this->assertSame(14, $this->countRequestsTo('searchNearby'));
    }

    public function test_repeated_normal_mode_requests_reuse_the_cached_sync(): void
    {
        $this->useGoogleProvider();
        Http::fake(['places.googleapis.com/v1/places:searchNearby' => Http::response($this->fakeGooglePlacesResponse())]);

        $this->getJson('/api/v1/places/nearby?'.http_build_query(self::BOX))->assertOk();
        $this->getJson('/api/v1/places/nearby?'.http_build_query(self::BOX))->assertOk();

        // 7 tiles synced once on the first call; the second identical call finds every tile
        // covered, so no additional requests.
        $this->assertSame(7, $this->countRequestsTo('searchNearby'));
    }

    private function countRequestsTo(string $needle): int
    {
        $count = 0;
        Http::assertSent(function ($request) use ($needle, &$count) {
            if (str_contains($request->url(), $needle)) {
                $count++;
            }

            return true;
        });

        return $count;
    }
}
