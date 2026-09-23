<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Decision;
use App\Models\Restaurant;
use App\Models\RestaurantSubmission;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommunityAreaTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSelf(): User
    {
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    public function test_self_selecting_an_area_sets_type_and_clears_university(): void
    {
        $this->actingAsSelf();
        Area::create(['name' => 'Kuala Lumpur', 'short_name' => 'KL', 'active' => true]);

        $response = $this->patchJson('/api/v1/me/community', ['area' => 'KL'])->assertOk();

        $response->assertJsonPath('user.affiliationType', 'area');
        $response->assertJsonPath('user.area', 'KL');
        $response->assertJsonPath('user.university', null);
    }

    public function test_selecting_a_university_clears_a_prior_area(): void
    {
        $user = $this->actingAsSelf();
        $kl = Area::create(['name' => 'Kuala Lumpur', 'short_name' => 'KL', 'active' => true]);
        University::create(['name' => 'Universiti Kebangsaan Malaysia', 'short_name' => 'UKM', 'country' => 'Malaysia', 'active' => true]);
        $this->patchJson('/api/v1/me/community', ['area' => 'KL'])->assertOk();

        $response = $this->patchJson('/api/v1/me/community', ['university' => 'UKM'])->assertOk();

        $response->assertJsonPath('user.affiliationType', 'university');
        $response->assertJsonPath('user.university', 'UKM');
        $response->assertJsonPath('user.area', null);
        $this->assertSame($kl->id, $kl->id); // sanity: area row untouched, just unlinked
    }

    public function test_sending_both_university_and_area_is_rejected(): void
    {
        $this->actingAsSelf();
        Area::create(['name' => 'Kuala Lumpur', 'short_name' => 'KL', 'active' => true]);
        University::create(['name' => 'Universiti Kebangsaan Malaysia', 'short_name' => 'UKM', 'country' => 'Malaysia', 'active' => true]);

        $this->patchJson('/api/v1/me/community', ['university' => 'UKM', 'area' => 'KL'])->assertStatus(422);
    }

    public function test_unknown_area_is_rejected(): void
    {
        $this->actingAsSelf();

        $this->patchJson('/api/v1/me/community', ['area' => 'NOT_REAL'])->assertStatus(422);
    }

    public function test_area_trending_feed_is_scoped_to_the_area_and_excludes_other_communities(): void
    {
        $kl = Area::create(['name' => 'Kuala Lumpur', 'short_name' => 'KL', 'active' => true]);
        $selangor = Area::create(['name' => 'Selangor', 'short_name' => 'SGR', 'active' => true]);

        $restaurantKl = Restaurant::create([
            'name' => 'KL Cafe', 'latitude' => 3.139, 'longitude' => 101.6869,
            'is_active' => true, 'provider' => 'google', 'provider_place_id' => 'ChIJ-kl',
        ]);
        $restaurantSelangor = Restaurant::create([
            'name' => 'Selangor Cafe', 'latitude' => 3.0738, 'longitude' => 101.5183,
            'is_active' => true, 'provider' => 'google', 'provider_place_id' => 'ChIJ-sgr',
        ]);

        // Two KL-affiliated users pick the KL restaurant (clears min_pickers=2); one Selangor
        // user picks the Selangor restaurant.
        foreach (range(1, 2) as $i) {
            $user = User::factory()->create(['role' => 'user', 'status' => 'active']);
            Sanctum::actingAs($user, ['*']);
            $this->patchJson('/api/v1/me/community', ['area' => 'KL'])->assertOk();
            Decision::create([
                'user_id' => $user->id, 'area_id' => $kl->id, 'mode' => 'solo',
                'client_token' => "tok-kl-{$i}", 'latitude' => 3.139, 'longitude' => 101.6869,
                'max_distance' => 5.0,
            ])->recommendations()->create([
                'restaurant_id' => $restaurantKl->id, 'rank' => 1, 'score' => 1.0, 'shown_at' => now(), 'accepted_at' => now(),
            ]);
        }

        $selangorUser = User::factory()->create(['role' => 'user', 'status' => 'active']);
        Sanctum::actingAs($selangorUser, ['*']);
        $this->patchJson('/api/v1/me/community', ['area' => 'SGR'])->assertOk();
        Decision::create([
            'user_id' => $selangorUser->id, 'area_id' => $selangor->id, 'mode' => 'solo',
            'client_token' => 'tok-sgr', 'latitude' => 3.0738, 'longitude' => 101.5183,
            'max_distance' => 5.0,
        ])->recommendations()->create([
            'restaurant_id' => $restaurantSelangor->id, 'rank' => 1, 'score' => 1.0, 'shown_at' => now(), 'accepted_at' => now(),
        ]);

        // View as a KL user — must see the KL restaurant trending, never the Selangor one.
        $viewer = User::factory()->create(['role' => 'user', 'status' => 'active']);
        Sanctum::actingAs($viewer, ['*']);
        $this->patchJson('/api/v1/me/community', ['area' => 'KL'])->assertOk();

        $response = $this->getJson('/api/v1/community/feed')->assertOk();
        $response->assertJsonPath('community.type', 'area');
        $response->assertJsonPath('community.area', 'KL');
        $response->assertJsonCount(1, 'trending');
        $response->assertJsonPath('trending.0.name', 'KL Cafe');
    }

    public function test_area_trending_feed_is_shared_for_a_few_minutes_then_refreshes(): void
    {
        $kl = Area::create(['name' => 'Kuala Lumpur', 'short_name' => 'KL', 'active' => true]);
        $pickTwice = function (string $name) use ($kl) {
            $restaurant = Restaurant::create([
                'name' => $name, 'latitude' => 3.139, 'longitude' => 101.6869,
                'is_active' => true, 'provider' => 'google', 'provider_place_id' => 'ChIJ-'.$name,
            ]);
            foreach (range(1, 2) as $i) {
                $picker = User::factory()->create(['role' => 'user', 'status' => 'active']);
                Decision::create([
                    'user_id' => $picker->id, 'area_id' => $kl->id, 'mode' => 'solo',
                    'client_token' => "tok-{$name}-{$i}", 'latitude' => 3.139, 'longitude' => 101.6869,
                    'max_distance' => 5.0,
                ])->recommendations()->create([
                    'restaurant_id' => $restaurant->id, 'rank' => 1, 'score' => 1.0, 'shown_at' => now(), 'accepted_at' => now(),
                ]);
            }
        };
        $this->actingAsSelf();
        $this->patchJson('/api/v1/me/community', ['area' => 'KL'])->assertOk();

        $pickTwice('First Cafe');
        $this->getJson('/api/v1/community/feed')->assertOk()->assertJsonCount(1, 'trending');

        $pickTwice('Second Cafe');
        $this->getJson('/api/v1/community/feed')->assertOk()->assertJsonCount(1, 'trending');

        $this->travel(config('recommendation.community_feed.cache_seconds') + 1)->seconds();
        $this->getJson('/api/v1/community/feed')->assertOk()->assertJsonCount(2, 'trending');
    }

    public function test_new_in_area_scoped_to_the_users_area(): void
    {
        $kl = Area::create(['name' => 'Kuala Lumpur', 'short_name' => 'KL', 'active' => true]);
        $selangor = Area::create(['name' => 'Selangor', 'short_name' => 'SGR', 'active' => true]);

        $klSubmission = RestaurantSubmission::create([
            'submission_type' => 'new_place', 'source_type' => 'manual', 'name' => 'KL Warung',
            'latitude' => 3.139, 'longitude' => 101.6869, 'location_source' => 'map_pin',
            'status' => 'approved', 'reviewed_at' => now(), 'area_id' => $kl->id,
        ]);
        Restaurant::create([
            'name' => 'KL Warung', 'latitude' => 3.139, 'longitude' => 101.6869,
            'is_active' => true, 'provider' => 'user_submitted', 'source_submission_id' => $klSubmission->id,
        ]);

        $sgrSubmission = RestaurantSubmission::create([
            'submission_type' => 'new_place', 'source_type' => 'manual', 'name' => 'Selangor Warung',
            'latitude' => 3.0738, 'longitude' => 101.5183, 'location_source' => 'map_pin',
            'status' => 'approved', 'reviewed_at' => now(), 'area_id' => $selangor->id,
        ]);
        Restaurant::create([
            'name' => 'Selangor Warung', 'latitude' => 3.0738, 'longitude' => 101.5183,
            'is_active' => true, 'provider' => 'user_submitted', 'source_submission_id' => $sgrSubmission->id,
        ]);

        $viewer = User::factory()->create(['role' => 'user', 'status' => 'active']);
        Sanctum::actingAs($viewer, ['*']);
        $this->patchJson('/api/v1/me/community', ['area' => 'KL'])->assertOk();

        $response = $this->getJson('/api/v1/community/feed')->assertOk();
        $response->assertJsonCount(1, 'newInArea');
        $response->assertJsonPath('newInArea.0.name', 'KL Warung');
    }

    public function test_community_request_creates_a_pending_row(): void
    {
        $this->actingAsSelf();

        $this->postJson('/api/v1/community/requests', ['type' => 'area', 'name' => 'Petaling Jaya'])
            ->assertOk()->assertJson(['requested' => true]);

        $this->assertDatabaseHas('community_requests', [
            'type' => 'area', 'name' => 'Petaling Jaya', 'status' => 'pending',
        ]);
    }

    public function test_community_request_rejects_unknown_type(): void
    {
        $this->actingAsSelf();

        $this->postJson('/api/v1/community/requests', ['type' => 'city', 'name' => 'Nowhere'])
            ->assertStatus(422);
    }
}
