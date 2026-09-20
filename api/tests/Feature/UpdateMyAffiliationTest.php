<?php

namespace Tests\Feature;

use App\Models\Decision;
use App\Models\University;
use App\Models\User;
use App\Models\UserAffiliation;
use Database\Seeders\RestaurantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UpdateMyAffiliationTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsPersistedUser(): User
    {
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    public function test_self_selecting_an_active_university_marks_it_self_reported(): void
    {
        $user = $this->actingAsPersistedUser();
        $ukm = University::create(['name' => 'Universiti Kebangsaan Malaysia', 'short_name' => 'UKM', 'country' => 'Malaysia', 'active' => true]);

        $response = $this->patchJson('/api/v1/me/community', ['university' => 'UKM'])->assertOk();

        $response->assertJsonPath('user.affiliationType', 'university');
        $response->assertJsonPath('user.university', 'UKM');
        $response->assertJsonPath('user.affiliationVerificationStatus', 'self_reported');

        $affiliation = UserAffiliation::where('user_id', $user->id)->first();
        $this->assertSame($ukm->id, $affiliation->university_id);
        $this->assertSame('self_reported', $affiliation->verification_method);
        $this->assertNull($affiliation->verified_at);
    }

    public function test_unknown_university_is_rejected(): void
    {
        $this->actingAsPersistedUser();

        $this->patchJson('/api/v1/me/community', ['university' => 'NOT_REAL'])->assertStatus(422);
    }

    public function test_inactive_university_is_rejected(): void
    {
        $this->actingAsPersistedUser();
        University::create(['name' => 'Retired University', 'short_name' => 'RETIRED', 'country' => 'Malaysia', 'active' => false]);

        $this->patchJson('/api/v1/me/community', ['university' => 'RETIRED'])->assertStatus(422);
    }

    public function test_null_university_is_an_explicit_public_selection(): void
    {
        $user = $this->actingAsPersistedUser();

        $response = $this->patchJson('/api/v1/me/community', ['university' => null])->assertOk();

        $response->assertJsonPath('user.affiliationType', 'public');
        $response->assertJsonPath('user.university', null);
        $response->assertJsonPath('user.affiliationVerificationStatus', 'self_reported');

        $affiliation = UserAffiliation::where('user_id', $user->id)->first();
        $this->assertSame('public', $affiliation->type);
        $this->assertNull($affiliation->university_id);
    }

    public function test_self_selecting_downgrades_a_prior_admin_verified_affiliation(): void
    {
        $user = $this->actingAsPersistedUser();
        $ukm = University::create(['name' => 'Universiti Kebangsaan Malaysia', 'short_name' => 'UKM', 'country' => 'Malaysia', 'active' => true]);
        $um = University::create(['name' => 'Universiti Malaya', 'short_name' => 'UM', 'country' => 'Malaysia', 'active' => true]);

        UserAffiliation::create([
            'user_id' => $user->id,
            'type' => 'university',
            'university_id' => $ukm->id,
            'verification_status' => 'verified',
            'verification_method' => 'admin_created',
            'verified_at' => now(),
        ]);

        $this->patchJson('/api/v1/me/community', ['university' => 'UM'])->assertOk();

        $affiliation = UserAffiliation::where('user_id', $user->id)->first();
        $this->assertSame($um->id, $affiliation->university_id);
        $this->assertSame('self_reported', $affiliation->verification_status);
        $this->assertSame('self_reported', $affiliation->verification_method);
        $this->assertNull($affiliation->verified_at);
    }

    public function test_switching_to_public_also_downgrades_a_prior_verified_affiliation(): void
    {
        $user = $this->actingAsPersistedUser();
        $ukm = University::create(['name' => 'Universiti Kebangsaan Malaysia', 'short_name' => 'UKM', 'country' => 'Malaysia', 'active' => true]);

        UserAffiliation::create([
            'user_id' => $user->id,
            'type' => 'university',
            'university_id' => $ukm->id,
            'verification_status' => 'verified',
            'verification_method' => 'admin_created',
            'verified_at' => now(),
        ]);

        $this->patchJson('/api/v1/me/community', ['university' => null])->assertOk();

        $affiliation = UserAffiliation::where('user_id', $user->id)->first();
        $this->assertSame('public', $affiliation->type);
        $this->assertSame('self_reported', $affiliation->verification_status);
        $this->assertNull($affiliation->verified_at);
    }

    public function test_update_response_matches_auth_me(): void
    {
        $this->actingAsPersistedUser();
        University::create(['name' => 'Universiti Kebangsaan Malaysia', 'short_name' => 'UKM', 'country' => 'Malaysia', 'active' => true]);

        $this->patchJson('/api/v1/me/community', ['university' => 'UKM'])->assertOk();

        $me = $this->getJson('/api/v1/auth/me')->assertOk();
        $me->assertJsonPath('user.affiliationType', 'university');
        $me->assertJsonPath('user.university', 'UKM');
        $me->assertJsonPath('user.affiliationVerificationStatus', 'self_reported');
    }

    public function test_changing_affiliation_does_not_rewrite_past_decision_snapshots(): void
    {
        $this->seed(RestaurantSeeder::class);
        $user = $this->actingAsPersistedUser();
        $ukm = University::create(['name' => 'Universiti Kebangsaan Malaysia', 'short_name' => 'UKM', 'country' => 'Malaysia', 'active' => true]);
        $um = University::create(['name' => 'Universiti Malaya', 'short_name' => 'UM', 'country' => 'Malaysia', 'active' => true]);

        $this->patchJson('/api/v1/me/community', ['university' => 'UKM'])->assertOk();

        $before = $this->postJson('/api/v1/recommendations/solo', [
            'latitude' => 2.928400,
            'longitude' => 101.780200,
            'budgetMax' => 3,
            'maxDistanceKm' => 5.0,
            'moods' => [],
        ])->json();

        $this->patchJson('/api/v1/me/community', ['university' => 'UM'])->assertOk();

        $after = $this->postJson('/api/v1/recommendations/solo', [
            'latitude' => 2.928400,
            'longitude' => 101.780200,
            'budgetMax' => 3,
            'maxDistanceKm' => 5.0,
            'moods' => [],
        ])->json();

        $this->assertSame($ukm->id, Decision::find($before['decisionId'])->university_id);
        $this->assertSame($um->id, Decision::find($after['decisionId'])->university_id);
    }
}
