<?php

namespace Tests\Feature;

use App\Services\PlacesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Nearby Search (New) caps results at 20 per call with no pagination — above
 * PlacesService::TILE_RADIUS_THRESHOLD_KM, a request should split into a fixed 7-circle hex
 * pattern (1 center + 6 ring) instead of one call that silently truncates coverage.
 */
class GridTilingTest extends TestCase
{
    use RefreshDatabase;

    private const LAT = 2.928400;

    private const LNG = 101.780200;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.places.provider' => 'google', 'services.places.google_api_key' => 'fake-key']);
    }

    private function place(string $id, string $name): array
    {
        return [
            'id' => $id,
            'displayName' => ['text' => $name],
            'location' => ['latitude' => self::LAT, 'longitude' => self::LNG],
            'types' => ['restaurant'],
            'rating' => 4.5,
        ];
    }

    private function nearbyCallCount(): int
    {
        $count = 0;
        Http::assertSent(function ($request) use (&$count) {
            if (str_contains($request->url(), 'searchNearby')) {
                $count++;
            }

            return true;
        });

        return $count;
    }

    public function test_radius_at_threshold_stays_a_single_call(): void
    {
        Http::fake(['*searchNearby*' => Http::response(['places' => [$this->place('p1', 'Corner Diner')]])]);

        app(PlacesService::class)->nearbyRestaurants(self::LAT, self::LNG, 1.0);

        $this->assertSame(1, $this->nearbyCallCount());
    }

    public function test_radius_above_threshold_tiles_into_seven_circles(): void
    {
        Http::fake(['*searchNearby*' => Http::response(['places' => [$this->place('p1', 'Corner Diner')]])]);

        app(PlacesService::class)->nearbyRestaurants(self::LAT, self::LNG, 5.0);

        $this->assertSame(7, $this->nearbyCallCount());
    }

    public function test_tiled_results_dedupe_by_provider_place_id(): void
    {
        // Every tile "sees" the same physical place near the shared center — must not become 7 rows.
        Http::fake(['*searchNearby*' => Http::response(['places' => [$this->place('same-place', 'Corner Diner')]])]);

        $restaurants = app(PlacesService::class)->nearbyRestaurants(self::LAT, self::LNG, 5.0);

        $matching = array_filter($restaurants, fn ($r) => $r['name'] === 'Corner Diner');
        $this->assertCount(1, $matching);
    }

    public function test_second_wide_search_in_the_same_area_re_hits_no_tiles(): void
    {
        Http::fake(['*searchNearby*' => Http::response(['places' => [$this->place('p1', 'Corner Diner')]])]);

        $service = app(PlacesService::class);
        $service->nearbyRestaurants(self::LAT, self::LNG, 5.0);
        $service->nearbyRestaurants(self::LAT, self::LNG, 5.0);

        // 7 tiles synced once each; the second identical request finds every tile's area already
        // covered within the cache window, so no additional Google calls.
        $this->assertSame(7, $this->nearbyCallCount());
    }
}
