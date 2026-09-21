<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Services\PlacesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Exercises `PlacesService::boundingBoxQuery()`'s degree-delta box plus the Haversine refinement
 * in each caller — via Reflection, since both read methods are private and the coverage/tiling
 * machinery around them (isAreaCovered, syncFromGoogle) is irrelevant to this specific logic.
 */
class PlacesServiceBoundingBoxTest extends TestCase
{
    use RefreshDatabase;

    private const LAT = 2.928400;

    private const LNG = 101.780200;

    private const RADIUS_KM = 2.0;

    private function restaurant(string $name, float $latitude, float $longitude, string $provider = 'user_submitted'): Restaurant
    {
        return Restaurant::create([
            'name' => $name,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'is_active' => true,
            'provider' => $provider,
            'provider_place_id' => $provider === 'google' ? 'places/'.$name : null,
        ]);
    }

    private function readUserSubmittedNear(float $latitude, float $longitude, float $radiusKm): array
    {
        $method = new ReflectionMethod(PlacesService::class, 'readUserSubmittedRestaurantsNear');
        $method->setAccessible(true);

        return $method->invoke(app(PlacesService::class), $latitude, $longitude, $radiusKm);
    }

    private function readGoogleNear(float $latitude, float $longitude, float $radiusKm, array $includedTypes = ['restaurant']): array
    {
        $method = new ReflectionMethod(PlacesService::class, 'readGoogleRestaurantsNear');
        $method->setAccessible(true);

        return $method->invoke(app(PlacesService::class), $latitude, $longitude, $radiusKm, $includedTypes);
    }

    private function latDelta(float $radiusKm): float
    {
        return $radiusKm / 111.0;
    }

    private function lngDelta(float $radiusKm, float $latitude): float
    {
        return $radiusKm / (111.320 * max(cos(deg2rad($latitude)), 0.01));
    }

    public function test_restaurants_just_inside_radius_in_every_cardinal_direction_are_included(): void
    {
        $latDelta = $this->latDelta(self::RADIUS_KM * 0.99);
        $lngDelta = $this->lngDelta(self::RADIUS_KM * 0.99, self::LAT);

        $this->restaurant('North', self::LAT + $latDelta, self::LNG);
        $this->restaurant('South', self::LAT - $latDelta, self::LNG);
        $this->restaurant('East', self::LAT, self::LNG + $lngDelta);
        $this->restaurant('West', self::LAT, self::LNG - $lngDelta);

        $names = array_column($this->readUserSubmittedNear(self::LAT, self::LNG, self::RADIUS_KM), 'name');

        $this->assertContains('North', $names);
        $this->assertContains('South', $names);
        $this->assertContains('East', $names);
        $this->assertContains('West', $names);
    }

    public function test_restaurant_in_box_corner_but_outside_the_radius_circle_is_excluded(): void
    {
        // Full lat AND lng delta simultaneously puts this inside the bounding box (whereBetween
        // is inclusive at the edges) but at ~radius*sqrt(2) from center — well outside the actual
        // circle. Proves the Haversine refinement still runs on rows the SQL pre-filter admits.
        $latDelta = $this->latDelta(self::RADIUS_KM);
        $lngDelta = $this->lngDelta(self::RADIUS_KM, self::LAT);

        $this->restaurant('Corner', self::LAT + $latDelta, self::LNG + $lngDelta);

        $names = array_column($this->readUserSubmittedNear(self::LAT, self::LNG, self::RADIUS_KM), 'name');

        $this->assertNotContains('Corner', $names);
    }

    public function test_restaurant_well_outside_radius_along_a_cardinal_direction_is_excluded(): void
    {
        $latDelta = $this->latDelta(self::RADIUS_KM * 1.05);

        $this->restaurant('TooFarNorth', self::LAT + $latDelta, self::LNG);

        $names = array_column($this->readUserSubmittedNear(self::LAT, self::LNG, self::RADIUS_KM), 'name');

        $this->assertNotContains('TooFarNorth', $names);
    }

    public function test_google_provider_restaurants_use_the_same_box_and_are_included(): void
    {
        $latDelta = $this->latDelta(self::RADIUS_KM * 0.99);

        $this->restaurant('GoogleNorth', self::LAT + $latDelta, self::LNG, provider: 'google');

        $names = array_column($this->readGoogleNear(self::LAT, self::LNG, self::RADIUS_KM), 'name');

        $this->assertContains('GoogleNorth', $names);
    }

    public function test_open_status_still_resolves_after_column_trim(): void
    {
        $restaurant = $this->restaurant('OpenNow', self::LAT, self::LNG);
        $restaurant->update(['opening_hours' => ['open_now' => true]]);

        $result = collect($this->readUserSubmittedNear(self::LAT, self::LNG, self::RADIUS_KM))
            ->firstWhere('name', 'OpenNow');

        // Regression guard for the select() column trim: opening_hours must still be hydrated
        // even though it's not a literal key in toRecommendationArray()'s return array — it's
        // read indirectly via Restaurant::openStatus().
        $this->assertSame('open', $result['open_status']);
    }
}
