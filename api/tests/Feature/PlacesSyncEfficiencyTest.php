<?php

namespace Tests\Feature;

use App\Models\PlaceSyncArea;
use App\Services\Craving\CravingResolver;
use App\Services\PlacesService;
use App\Support\DiscoveryMode;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlacesSyncEfficiencyTest extends TestCase
{
    use RefreshDatabase;

    private const LAT = 2.928400;

    private const LNG = 101.780200;

    private const BASE_TYPES = ['restaurant', 'meal_takeaway', 'meal_delivery', 'food_court'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.places.provider' => 'google', 'services.places.google_api_key' => 'fake-key']);
    }

    private function googlePlace(string $id, string $name, array $extra = []): array
    {
        return [
            'id' => $id,
            'displayName' => ['text' => $name],
            'location' => ['latitude' => self::LAT, 'longitude' => self::LNG],
            'types' => ['restaurant'],
            'rating' => 4.5,
            ...$extra,
        ];
    }

    private function syncedAreaEastOfCenter(float $km, float $radiusKm): void
    {
        PlaceSyncArea::create([
            'provider' => 'google',
            'latitude' => self::LAT,
            'longitude' => self::LNG + $km / (111.320 * cos(deg2rad(self::LAT))),
            'radius_km' => $radiusKm,
            'synced_at' => now(),
            'types' => self::BASE_TYPES,
        ]);
    }

    public function test_request_covered_by_the_union_of_synced_areas_does_not_call_google(): void
    {
        Http::fake();
        // Neither circle alone contains the 0.5km request, but together they do.
        $this->syncedAreaEastOfCenter(0.6, 1.0);
        $this->syncedAreaEastOfCenter(-0.6, 1.0);

        app(PlacesService::class)->nearbyRestaurants(self::LAT, self::LNG, 0.5);

        Http::assertNothingSent();
    }

    public function test_gap_between_synced_areas_still_syncs(): void
    {
        Http::fake(['*searchNearby*' => Http::response(['places' => []])]);
        $this->syncedAreaEastOfCenter(1.5, 1.0);
        $this->syncedAreaEastOfCenter(-1.5, 1.0);

        app(PlacesService::class)->nearbyRestaurants(self::LAT, self::LNG, 0.5);

        Http::assertSentCount(1);
    }

    public function test_cached_text_search_does_not_rewrite_restaurants(): void
    {
        Http::fake([
            '*searchNearby*' => Http::response(['places' => []]),
            '*searchText*' => Http::response(['places' => [$this->googlePlace('scoop-1', 'Inside Scoop', ['types' => ['ice_cream_shop']])]]),
        ]);
        $craving = (new CravingResolver)->resolve('ice cream');
        $service = app(PlacesService::class);
        $service->nearbyRestaurants(self::LAT, self::LNG, 0.8, $craving);

        $restaurantWrites = 0;
        DB::listen(function ($query) use (&$restaurantWrites) {
            if (preg_match('/^(insert into|update) "restaurants"/', $query->sql)) {
                $restaurantWrites++;
            }
        });
        $restaurants = $service->nearbyRestaurants(self::LAT, self::LNG, 0.8, $craving);

        $this->assertSame(0, $restaurantWrites);
        $this->assertContains('Inside Scoop', array_column($restaurants, 'name'));
    }

    public function test_one_failing_text_search_lane_does_not_drop_the_others(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'searchNearby')) {
                return Http::response(['places' => []]);
            }

            return match ($request['textQuery']) {
                'specialty coffee' => Http::response(['error' => 'boom'], 500),
                'cafe' => Http::response(['places' => [$this->googlePlace('cafe-1', 'Quiet Corner Cafe', ['types' => ['cafe']])]]),
                default => Http::response(['places' => [$this->googlePlace('scoop-1', 'Inside Scoop', ['types' => ['ice_cream_shop']])]]),
            };
        });

        $restaurants = app(PlacesService::class)->nearbyRestaurants(
            self::LAT, self::LNG, 0.8, (new CravingResolver)->resolve('ice cream'), DiscoveryMode::LowKey
        );

        $names = array_column($restaurants, 'name');
        $this->assertContains('Quiet Corner Cafe', $names);
        $this->assertContains('Inside Scoop', $names);
    }

    public function test_open_status_follows_weekly_hours_not_the_sync_time_snapshot(): void
    {
        // Synced at Wednesday lunch, when Google said openNow=true.
        $this->travelTo(CarbonImmutable::parse('2026-09-23 12:30', 'Asia/Kuala_Lumpur'));
        Http::fake(['*searchNearby*' => Http::response(['places' => [$this->googlePlace('lunch-1', 'Lunch Only Kedai', [
            'currentOpeningHours' => ['openNow' => true],
            'utcOffsetMinutes' => 480,
            'regularOpeningHours' => ['periods' => array_map(fn (int $day) => [
                'open' => ['day' => $day, 'hour' => 11, 'minute' => 0],
                'close' => ['day' => $day, 'hour' => 15, 'minute' => 0],
            ], [1, 2, 3, 4, 5])],
        ])]])]);
        $service = app(PlacesService::class);

        $this->assertSame('open', $service->nearbyRestaurants(self::LAT, self::LNG, 0.8)[0]['open_status']);

        // Same day, 17:00 — still inside the area-sync cache window, so no re-sync happens.
        $this->travelTo(CarbonImmutable::parse('2026-09-23 17:00', 'Asia/Kuala_Lumpur'));

        $this->assertSame('closed', $service->nearbyRestaurants(self::LAT, self::LNG, 0.8)[0]['open_status']);
        Http::assertSentCount(1);
    }
}
