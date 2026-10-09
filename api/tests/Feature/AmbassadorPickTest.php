<?php

namespace Tests\Feature;

use App\Filament\Resources\AmbassadorPicks\Pages\ListAmbassadorPicks;
use App\Models\AdminAuditLog;
use App\Models\AmbassadorPick;
use App\Models\Restaurant;
use App\Models\University;
use App\Models\User;
use App\Models\UserAffiliation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class AmbassadorPickTest extends TestCase
{
    use RefreshDatabase;

    private function university(string $shortName = 'UKM'): University
    {
        return University::create(['name' => "Universiti {$shortName}", 'short_name' => $shortName, 'country' => 'Malaysia', 'active' => true]);
    }

    private function member(University $university): User
    {
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);
        UserAffiliation::create(['user_id' => $user->id, 'type' => 'university', 'university_id' => $university->id]);

        return $user->fresh();
    }

    private function ambassadorOf(University $university): User
    {
        return User::factory()->create(['role' => 'user', 'status' => 'active', 'name' => 'Aiman', 'ambassador_university_id' => $university->id]);
    }

    private function restaurant(string $name = 'Nasi Kukus Aunty'): Restaurant
    {
        return Restaurant::create(['name' => $name, 'latitude' => 2.93, 'longitude' => 101.78, 'is_active' => true, 'provider' => 'google', 'provider_place_id' => 'ChIJ-'.md5($name)]);
    }

    private function addPick(User $ambassador, Restaurant $restaurant, ?string $note = null)
    {
        Sanctum::actingAs($ambassador, ['*']);

        return $this->postJson('/api/v1/me/ambassador-picks', ['restaurantId' => $restaurant->id, 'note' => $note]);
    }

    public function test_members_see_their_ambassadors_pick_with_note_in_the_feed(): void
    {
        $ukm = $this->university();
        $this->addPick($this->ambassadorOf($ukm), $this->restaurant(), 'Get the ayam berempah')->assertCreated();
        Sanctum::actingAs($this->member($ukm), ['*']);

        $response = $this->getJson('/api/v1/community/feed');

        $response->assertOk()
            ->assertJsonCount(1, 'ambassadorPicks')
            ->assertJsonPath('ambassadorPicks.0.name', 'Nasi Kukus Aunty')
            ->assertJsonPath('ambassadorPicks.0.note', 'Get the ayam berempah')
            ->assertJsonPath('ambassadorPicks.0.ambassador.name', 'Aiman');
    }

    public function test_members_of_another_community_do_not_see_the_picks(): void
    {
        $ukm = $this->university();
        $this->addPick($this->ambassadorOf($ukm), $this->restaurant())->assertCreated();
        Sanctum::actingAs($this->member($this->university('UM')), ['*']);

        $response = $this->getJson('/api/v1/community/feed');

        $response->assertOk()->assertJsonCount(0, 'ambassadorPicks');
    }

    public function test_picks_disappear_once_the_ambassador_is_reassigned(): void
    {
        $ukm = $this->university();
        $ambassador = $this->ambassadorOf($ukm);
        $this->addPick($ambassador, $this->restaurant())->assertCreated();
        $ambassador->update(['ambassador_university_id' => $this->university('UM')->id]);
        Sanctum::actingAs($this->member($ukm), ['*']);

        $response = $this->getJson('/api/v1/community/feed');

        $response->assertOk()->assertJsonCount(0, 'ambassadorPicks');
    }

    public function test_returns_403_when_a_non_ambassador_adds_a_pick(): void
    {
        $member = $this->member($this->university());

        $response = $this->addPick($member, $this->restaurant());

        $response->assertForbidden();
        $this->assertSame(0, AmbassadorPick::count());
    }

    public function test_returns_422_for_a_pick_beyond_the_limit(): void
    {
        $ambassador = $this->ambassadorOf($this->university());
        foreach (range(1, AmbassadorPick::MAX_PER_AMBASSADOR) as $i) {
            $this->addPick($ambassador, $this->restaurant("Place {$i}"))->assertCreated();
        }

        $response = $this->addPick($ambassador, $this->restaurant('One too many'));

        $response->assertUnprocessable()->assertJsonPath('message', "You've used all 8 picks. Remove one to add another.");
        $this->assertSame(AmbassadorPick::MAX_PER_AMBASSADOR, AmbassadorPick::count());
    }

    public function test_returns_422_when_the_note_contains_a_link(): void
    {
        $ambassador = $this->ambassadorOf($this->university());

        $response = $this->addPick($ambassador, $this->restaurant(), 'Order at www.example.com');

        $response->assertUnprocessable()->assertJsonPath('message', "Links aren't allowed in community posts.");
        $this->assertSame(0, AmbassadorPick::count());
    }

    public function test_picking_the_same_place_again_updates_the_note(): void
    {
        $ambassador = $this->ambassadorOf($this->university());
        $restaurant = $this->restaurant();
        $this->addPick($ambassador, $restaurant, 'First note')->assertCreated();

        $response = $this->addPick($ambassador, $restaurant, 'Better note');

        $response->assertOk()->assertJsonPath('pick.note', 'Better note');
        $this->assertSame(1, AmbassadorPick::count());
    }

    public function test_removing_a_pick_deletes_it(): void
    {
        $ambassador = $this->ambassadorOf($this->university());
        $restaurant = $this->restaurant();
        $this->addPick($ambassador, $restaurant)->assertCreated();

        $response = $this->deleteJson("/api/v1/me/ambassador-picks/{$restaurant->id}");

        $response->assertOk()->assertJsonPath('removed', true);
        $this->assertSame(0, AmbassadorPick::count());
    }

    public function test_index_lists_the_ambassadors_current_picks_with_notes(): void
    {
        $ambassador = $this->ambassadorOf($this->university());
        $restaurant = $this->restaurant();
        $this->addPick($ambassador, $restaurant, 'Go early')->assertCreated();

        $response = $this->getJson('/api/v1/me/ambassador-picks');

        $response->assertOk()
            ->assertJsonPath('picks', [['restaurantId' => $restaurant->id, 'note' => 'Go early']])
            ->assertJsonPath('max', 8);
    }

    public function test_admin_remove_action_deletes_the_pick_and_audits(): void
    {
        $ambassador = $this->ambassadorOf($this->university());
        $this->addPick($ambassador, $this->restaurant(), 'Rude note')->assertCreated();
        $pick = AmbassadorPick::first();
        $this->actingAs(User::factory()->create(['role' => 'superadmin', 'status' => 'active']), 'web');

        Livewire::test(ListAmbassadorPicks::class)->callTableAction('remove', $pick);

        $this->assertModelMissing($pick);
        $this->assertSame(1, AdminAuditLog::where('action', 'ambassador_pick.remove')->count());
    }
}
