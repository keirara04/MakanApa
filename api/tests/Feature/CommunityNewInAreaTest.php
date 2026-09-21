<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantSubmission;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommunityNewInAreaTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsPublicUser(): User
    {
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function approvedSubmission(array $overrides = []): RestaurantSubmission
    {
        return RestaurantSubmission::create(array_merge([
            'submission_type' => 'new_place',
            'source_type' => 'manual',
            'name' => 'Warung Test',
            'latitude' => 2.928400,
            'longitude' => 101.780200,
            'location_source' => 'map_pin',
            'status' => 'approved',
            'reviewed_at' => now(),
        ], $overrides));
    }

    public function test_recently_approved_community_place_appears_in_new_in_area(): void
    {
        $this->actingAsPublicUser();
        $submission = $this->approvedSubmission();

        Restaurant::create([
            'name' => 'Warung Test',
            'latitude' => 2.928400,
            'longitude' => 101.780200,
            'is_active' => true,
            'provider' => 'user_submitted',
            'source_submission_id' => $submission->id,
        ]);

        $response = $this->getJson('/api/v1/community/feed?latitude=2.9284&longitude=101.7802')->assertOk();

        $response->assertJsonCount(1, 'newInArea');
        $response->assertJsonPath('newInArea.0.name', 'Warung Test');
    }

    public function test_google_synced_restaurant_is_not_treated_as_new_in_area(): void
    {
        $this->actingAsPublicUser();

        Restaurant::create([
            'name' => 'Google Synced Cafe',
            'latitude' => 2.928400,
            'longitude' => 101.780200,
            'is_active' => true,
            'provider' => 'google',
            'provider_place_id' => 'ChIJ-fake',
        ]);

        $response = $this->getJson('/api/v1/community/feed?latitude=2.9284&longitude=101.7802')->assertOk();

        $response->assertJsonCount(0, 'newInArea');
    }

    public function test_community_submission_sourced_from_google_still_counts_as_new_in_area(): void
    {
        // approveNewPlace() sets provider='google' when the submitter found the place via
        // Google search — the common AddPlaceFlow path. This must still surface here; only
        // provider='google' rows with NO source_submission_id (background sync) are excluded.
        $this->actingAsPublicUser();
        $submission = $this->approvedSubmission(['source_type' => 'google', 'google_place_id' => 'ChIJ-community']);

        Restaurant::create([
            'name' => 'Warung Test',
            'latitude' => 2.928400,
            'longitude' => 101.780200,
            'is_active' => true,
            'provider' => 'google',
            'provider_place_id' => 'ChIJ-community',
            'source_submission_id' => $submission->id,
        ]);

        $response = $this->getJson('/api/v1/community/feed?latitude=2.9284&longitude=101.7802')->assertOk();

        $response->assertJsonCount(1, 'newInArea');
        $response->assertJsonPath('newInArea.0.name', 'Warung Test');
    }

    public function test_stale_community_place_drops_out_of_new_in_area(): void
    {
        $this->actingAsPublicUser();
        $submission = $this->approvedSubmission();

        $restaurant = Restaurant::create([
            'name' => 'Old News Cafe',
            'latitude' => 2.928400,
            'longitude' => 101.780200,
            'is_active' => true,
            'provider' => 'user_submitted',
            'source_submission_id' => $submission->id,
        ]);
        $restaurant->forceFill(['created_at' => now()->subDays(30)])->save();

        $response = $this->getJson('/api/v1/community/feed?latitude=2.9284&longitude=101.7802')->assertOk();

        $response->assertJsonCount(0, 'newInArea');
    }

    public function test_far_away_community_place_is_excluded_for_public_users(): void
    {
        $this->actingAsPublicUser();
        $submission = $this->approvedSubmission(['latitude' => 3.5, 'longitude' => 103.5]);

        Restaurant::create([
            'name' => 'Far Away Cafe',
            'latitude' => 3.5,
            'longitude' => 103.5,
            'is_active' => true,
            'provider' => 'user_submitted',
            'source_submission_id' => $submission->id,
        ]);

        $response = $this->getJson('/api/v1/community/feed?latitude=2.9284&longitude=101.7802')->assertOk();

        $response->assertJsonCount(0, 'newInArea');
    }

    public function test_university_user_sees_new_in_area_scoped_to_their_university(): void
    {
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);
        Sanctum::actingAs($user, ['*']);
        $ukm = University::create(['name' => 'Universiti Kebangsaan Malaysia', 'short_name' => 'UKM', 'country' => 'Malaysia', 'active' => true]);
        $um = University::create(['name' => 'Universiti Malaya', 'short_name' => 'UM', 'country' => 'Malaysia', 'active' => true]);
        $this->patchJson('/api/v1/me/community', ['university' => 'UKM'])->assertOk();

        $ukmSubmission = $this->approvedSubmission(['university_id' => $ukm->id, 'name' => 'UKM Warung']);
        Restaurant::create([
            'name' => 'UKM Warung', 'latitude' => 2.9284, 'longitude' => 101.7802,
            'is_active' => true, 'provider' => 'user_submitted', 'source_submission_id' => $ukmSubmission->id,
        ]);

        $umSubmission = $this->approvedSubmission(['university_id' => $um->id, 'name' => 'UM Warung']);
        Restaurant::create([
            'name' => 'UM Warung', 'latitude' => 2.9284, 'longitude' => 101.7802,
            'is_active' => true, 'provider' => 'user_submitted', 'source_submission_id' => $umSubmission->id,
        ]);

        $response = $this->getJson('/api/v1/community/feed')->assertOk();

        $response->assertJsonCount(1, 'newInArea');
        $response->assertJsonPath('newInArea.0.name', 'UKM Warung');
    }
}
