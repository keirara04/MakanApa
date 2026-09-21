<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantSave;
use App\Models\User;
use App\Services\PlacesService;
use App\Services\RestaurantMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class RestaurantMergeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'superadmin', 'status' => 'active']);
    }

    public function test_merge_moves_saves_and_deduplicates_by_installation_id(): void
    {
        $keep = Restaurant::create(['name' => 'Keep', 'latitude' => 2.9, 'longitude' => 101.7, 'is_active' => true, 'provider' => 'user_submitted']);
        $mergeIn = Restaurant::create(['name' => 'Duplicate', 'latitude' => 2.9, 'longitude' => 101.7, 'is_active' => true, 'provider' => 'user_submitted']);

        RestaurantSave::create(['restaurant_id' => $keep->id, 'installation_id' => 'shared-device']);
        RestaurantSave::create(['restaurant_id' => $mergeIn->id, 'installation_id' => 'shared-device']); // collides
        RestaurantSave::create(['restaurant_id' => $mergeIn->id, 'installation_id' => 'other-device']); // moves cleanly

        app(RestaurantMergeService::class)->merge($keep, $mergeIn, $this->admin());

        $this->assertSame(2, RestaurantSave::where('restaurant_id', $keep->id)->count());
        $this->assertSame(0, RestaurantSave::where('restaurant_id', $mergeIn->id)->count());
        $this->assertFalse($mergeIn->fresh()->is_active);
        $this->assertSame($keep->id, $mergeIn->fresh()->merged_into_restaurant_id);
    }

    public function test_cannot_merge_into_an_already_merged_restaurant(): void
    {
        $canonical = Restaurant::create(['name' => 'Canonical', 'latitude' => 2.9, 'longitude' => 101.7, 'is_active' => true, 'provider' => 'user_submitted']);
        $mergedAway = Restaurant::create(['name' => 'Already merged', 'latitude' => 2.9, 'longitude' => 101.7, 'is_active' => false, 'provider' => 'user_submitted', 'merged_into_restaurant_id' => $canonical->id]);
        $thirdDuplicate = Restaurant::create(['name' => 'Third duplicate', 'latitude' => 2.9, 'longitude' => 101.7, 'is_active' => true, 'provider' => 'user_submitted']);

        $this->expectException(RuntimeException::class);
        app(RestaurantMergeService::class)->merge($mergedAway, $thirdDuplicate, $this->admin());
    }

    public function test_google_sync_never_reactivates_a_merged_away_restaurant(): void
    {
        $canonical = Restaurant::create([
            'name' => 'Canonical Cafe', 'latitude' => 2.9284, 'longitude' => 101.7802,
            'is_active' => true, 'provider' => 'google', 'provider_place_id' => 'ChIJ-canonical',
        ]);
        $mergedAway = Restaurant::create([
            'name' => 'Duplicate Cafe', 'latitude' => 2.9284, 'longitude' => 101.7802,
            'is_active' => true, 'provider' => 'google', 'provider_place_id' => 'ChIJ-duplicate',
        ]);

        app(RestaurantMergeService::class)->merge($canonical, $mergedAway, $this->admin());
        $this->assertFalse($mergedAway->fresh()->is_active);

        // Simulate a background sync finding this exact place again via Google, still keyed by
        // the merged-away row's own provider_place_id — it must update the canonical restaurant,
        // never flip is_active back to true on the merged-away row.
        $service = app(PlacesService::class);
        $upsert = new ReflectionMethod($service, 'upsertRestaurant');
        $upsert->invoke($service, [
            'name' => 'Duplicate Cafe (renamed on Google)',
            'latitude' => 2.9284,
            'longitude' => 101.7802,
            'price_level' => 2,
            'rating' => 4.2,
            'user_rating_count' => 10,
            'google_types' => ['cafe'],
            'is_active' => true,
            'opening_hours' => ['open_now' => true],
            'food_category' => 'Cafe',
            'provider_place_id' => 'ChIJ-duplicate',
            'cuisines' => [],
            'tags' => [],
        ]);

        $this->assertFalse($mergedAway->fresh()->is_active, 'merged-away row must never be reactivated by sync');
        $this->assertTrue($canonical->fresh()->is_active);
        $this->assertSame('Duplicate Cafe (renamed on Google)', $canonical->fresh()->name, 'sync should redirect field updates onto the canonical row');
    }
}
