<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantFieldOverride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminRemoveRestaurantTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperadmin(): User
    {
        $admin = User::factory()->create(['role' => 'superadmin', 'status' => 'active']);
        Sanctum::actingAs($admin, ['*']);

        return $admin;
    }

    public function test_admin_can_remove_a_community_submitted_restaurant(): void
    {
        $this->actingAsSuperadmin();
        $restaurant = Restaurant::create([
            'name' => 'Warung Test', 'latitude' => 2.9284, 'longitude' => 101.7802,
            'is_active' => true, 'provider' => 'user_submitted',
        ]);

        $this->postJson("/api/v1/admin/community/restaurants/{$restaurant->id}/remove")->assertOk()
            ->assertJson(['removed' => true]);

        $this->assertFalse($restaurant->fresh()->is_active);
    }

    public function test_removing_a_google_provider_restaurant_creates_a_protective_override(): void
    {
        $this->actingAsSuperadmin();
        $restaurant = Restaurant::create([
            'name' => 'Google Cafe', 'latitude' => 2.9284, 'longitude' => 101.7802,
            'is_active' => true, 'provider' => 'google', 'provider_place_id' => 'ChIJ-fake',
        ]);

        $this->postJson("/api/v1/admin/community/restaurants/{$restaurant->id}/remove")->assertOk();

        $this->assertFalse($restaurant->fresh()->is_active);
        $override = RestaurantFieldOverride::where('restaurant_id', $restaurant->id)->where('field', 'is_active')->first();
        $this->assertNotNull($override);
        $this->assertFalse((bool) $override->value);
        $this->assertSame('admin', $override->authority);
    }

    public function test_removed_restaurant_no_longer_appears_in_new_in_area(): void
    {
        $this->actingAsSuperadmin();
        $restaurant = Restaurant::create([
            'name' => 'Warung Test', 'latitude' => 2.9284, 'longitude' => 101.7802,
            'is_active' => true, 'provider' => 'user_submitted',
        ]);

        $this->postJson("/api/v1/admin/community/restaurants/{$restaurant->id}/remove")->assertOk();

        $response = $this->getJson('/api/v1/community/feed?latitude=2.9284&longitude=101.7802')->assertOk();
        $response->assertJsonCount(0, 'newInArea');
    }

    public function test_non_admin_cannot_remove_a_restaurant(): void
    {
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);
        Sanctum::actingAs($user, ['*']);
        $restaurant = Restaurant::create([
            'name' => 'Warung Test', 'latitude' => 2.9284, 'longitude' => 101.7802,
            'is_active' => true, 'provider' => 'user_submitted',
        ]);

        $this->postJson("/api/v1/admin/community/restaurants/{$restaurant->id}/remove")->assertForbidden();

        $this->assertTrue($restaurant->fresh()->is_active);
    }
}
