<?php

namespace Tests\Feature;

use App\Filament\Resources\Restaurants\Pages\EditRestaurant;
use App\Filament\Resources\RestaurantSubmissions\Pages\ListRestaurantSubmissions;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\AdminAuditLog;
use App\Models\Area;
use App\Models\Restaurant;
use App\Models\RestaurantFieldOverride;
use App\Models\RestaurantSubmission;
use App\Models\University;
use App\Models\User;
use App\Services\PlacesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fires the actual Filament Actions (not just page renders) to prove the panel wiring calls
 * the same services the JSON API uses, and that every mutation gets an audit log row.
 */
class AdminPanelActionsTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        return User::factory()->create(['role' => 'superadmin', 'status' => 'active']);
    }

    public function test_deactivate_action_on_restaurant_edit_page(): void
    {
        $admin = $this->superadmin();
        $restaurant = Restaurant::create([
            'name' => 'Action Test Cafe', 'latitude' => 2.9, 'longitude' => 101.7,
            'is_active' => true, 'provider' => 'user_submitted',
        ]);

        $this->actingAs($admin, 'web');

        Livewire::test(EditRestaurant::class, ['record' => $restaurant->id])
            ->callAction('deactivate');

        $this->assertFalse($restaurant->fresh()->is_active);
        $this->assertSame(1, AdminAuditLog::where('action', 'restaurant.remove')->count());
    }

    public function test_an_admin_edit_to_a_google_place_survives_the_next_sync(): void
    {
        config(['services.places.provider' => 'google', 'services.places.google_api_key' => 'fake-key']);
        $restaurant = Restaurant::create([
            'name' => 'Wrong Name', 'latitude' => 2.9284, 'longitude' => 101.7802, 'is_active' => true,
            'provider' => 'google', 'provider_place_id' => 'ChIJ-edit',
        ]);
        $this->actingAs($this->superadmin(), 'web');

        Livewire::test(EditRestaurant::class, ['record' => $restaurant->id])
            ->fillForm(['name' => 'Right Name'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['name'], RestaurantFieldOverride::where('restaurant_id', $restaurant->id)->pluck('field')->all());
        $this->assertSame(1, AdminAuditLog::where('action', 'restaurant.edit')->count());

        Http::fake(['*searchNearby*' => Http::response(['places' => [[
            'id' => 'ChIJ-edit', 'displayName' => ['text' => 'Wrong Name'],
            'location' => ['latitude' => 2.9284, 'longitude' => 101.7802], 'types' => ['restaurant'],
        ]]])]);
        app(PlacesService::class)->nearbyRestaurants(2.9284, 101.7802, 1.0);

        $this->assertNotNull($restaurant->fresh()->last_synced_at); // the sync did reach this row
        $this->assertSame('Right Name', $restaurant->fresh()->name);
    }

    public function test_saving_without_changes_pins_nothing(): void
    {
        $restaurant = Restaurant::create([
            'name' => 'Untouched', 'latitude' => 2.9284123, 'longitude' => 101.7802456, 'is_active' => true,
            'provider' => 'google', 'provider_place_id' => 'ChIJ-same', 'address' => 'Jalan 1', 'price_level' => 2,
            'opening_hours' => ['open_now' => true, 'periods' => []],
        ]);
        $this->actingAs($this->superadmin(), 'web');

        Livewire::test(EditRestaurant::class, ['record' => $restaurant->id])->call('save')->assertHasNoFormErrors();

        $this->assertSame(0, RestaurantFieldOverride::where('restaurant_id', $restaurant->id)->count());
    }

    public function test_approve_action_on_submissions_list(): void
    {
        $admin = $this->superadmin();
        $university = University::create(['name' => 'Test University', 'short_name' => 'UTest', 'active' => true]);
        $submission = RestaurantSubmission::create([
            'submission_type' => 'new_place', 'source_type' => 'manual', 'status' => 'pending',
            'name' => 'Brand New Place', 'latitude' => 2.9, 'longitude' => 101.7,
            'location_source' => 'map_pin', 'university_id' => $university->id,
        ]);

        $this->actingAs($admin, 'web');

        Livewire::test(ListRestaurantSubmissions::class)
            ->callTableAction('approve', $submission, data: ['releaseToGoogle' => false]);

        $this->assertSame('approved', $submission->fresh()->status);
        $this->assertNotNull($submission->fresh()->restaurant_id);
        $this->assertSame(1, AdminAuditLog::where('action', 'submission.approve')->count());
    }

    public function test_suspend_action_on_user_edit_page(): void
    {
        $admin = $this->superadmin();
        $target = User::factory()->create(['role' => 'user', 'status' => 'active']);

        $this->actingAs($admin, 'web');

        Livewire::test(EditUser::class, ['record' => $target->id])
            ->callAction('suspend', data: ['reason' => 'Spam reports']);

        $this->assertSame('suspended', $target->fresh()->status);
        $this->assertSame(1, AdminAuditLog::where('action', 'user.suspend')->count());
    }

    public function test_suspend_action_blocked_for_last_active_superadmin(): void
    {
        $onlySuperadmin = $this->superadmin();
        $actingAsSomeoneElse = User::factory()->create(['role' => 'user', 'status' => 'active']);

        $this->actingAs($actingAsSomeoneElse, 'web');

        Livewire::test(EditUser::class, ['record' => $onlySuperadmin->id])
            ->callAction('suspend', data: ['reason' => 'test']);

        $this->assertSame('active', $onlySuperadmin->fresh()->status, 'guard must block suspending the last active superadmin');
        $this->assertSame(0, AdminAuditLog::where('action', 'user.suspend')->count());
    }

    public function test_edit_user_form_changes_password_revokes_sessions_and_audits(): void
    {
        $admin = $this->superadmin();
        $target = User::factory()->create(['role' => 'user', 'status' => 'active', 'password' => Hash::make('old-password')]);
        $target->createToken('device-a');
        $oldHash = $target->password;

        $this->actingAs($admin, 'web');

        Livewire::test(EditUser::class, ['record' => $target->id])
            ->fillForm(['password' => 'brand-new-password'])
            ->call('save');

        $target->refresh();
        $this->assertNotSame($oldHash, $target->password);
        $this->assertTrue(Hash::check('brand-new-password', $target->password));
        $this->assertSame(0, $target->tokens()->count());
        $this->assertSame(1, AdminAuditLog::where('action', 'user.change_password')->count());
    }

    public function test_edit_user_form_without_password_field_leaves_password_untouched(): void
    {
        $admin = $this->superadmin();
        $target = User::factory()->create(['role' => 'user', 'status' => 'active', 'password' => Hash::make('old-password')]);
        $oldHash = $target->password;

        $this->actingAs($admin, 'web');

        Livewire::test(EditUser::class, ['record' => $target->id])
            ->fillForm(['name' => 'Updated Name'])
            ->call('save');

        $target->refresh();
        $this->assertSame($oldHash, $target->password);
        $this->assertSame(0, AdminAuditLog::where('action', 'user.change_password')->count());
    }

    public function test_set_ambassador_action_assigns_a_university_and_audits(): void
    {
        $admin = $this->superadmin();
        $target = User::factory()->create();
        $ukm = University::create(['name' => 'Universiti Kebangsaan Malaysia', 'short_name' => 'UKM', 'active' => true]);
        $this->actingAs($admin, 'web');

        Livewire::test(EditUser::class, ['record' => $target->id])
            ->callAction('setAmbassador', data: ['type' => 'university', 'university_id' => $ukm->id]);

        $this->assertSame($ukm->id, $target->fresh()->ambassador_university_id);
        $this->assertNull($target->fresh()->ambassador_area_id);
        $this->assertSame(1, AdminAuditLog::where('action', 'user.set_ambassador')->count());
    }

    public function test_switching_ambassador_to_an_area_clears_the_university(): void
    {
        $admin = $this->superadmin();
        $ukm = University::create(['name' => 'Universiti Kebangsaan Malaysia', 'short_name' => 'UKM', 'active' => true]);
        $bangi = Area::create(['name' => 'Bangi', 'short_name' => 'Bangi', 'active' => true]);
        $target = User::factory()->create(['ambassador_university_id' => $ukm->id]);
        $this->actingAs($admin, 'web');

        Livewire::test(EditUser::class, ['record' => $target->id])
            ->callAction('setAmbassador', data: ['type' => 'area', 'area_id' => $bangi->id]);

        $this->assertNull($target->fresh()->ambassador_university_id);
        $this->assertSame($bangi->id, $target->fresh()->ambassador_area_id);
    }

    public function test_setting_ambassador_to_none_removes_it(): void
    {
        $admin = $this->superadmin();
        $bangi = Area::create(['name' => 'Bangi', 'short_name' => 'Bangi', 'active' => true]);
        $target = User::factory()->create(['ambassador_area_id' => $bangi->id]);
        $this->actingAs($admin, 'web');

        Livewire::test(EditUser::class, ['record' => $target->id])
            ->callAction('setAmbassador', data: ['type' => 'none']);

        $this->assertNull($target->fresh()->ambassador_university_id);
        $this->assertNull($target->fresh()->ambassador_area_id);
    }
}
