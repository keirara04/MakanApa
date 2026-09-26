<?php

namespace Tests\Feature;

use App\Filament\Pages\GuestConversion;
use App\Filament\Pages\Retention;
use App\Filament\Resources\Restaurants\Pages\CreateRestaurant;
use App\Filament\Resources\Restaurants\Pages\ListRestaurants;
use App\Filament\Resources\Restaurants\RestaurantResource;
use App\Filament\Resources\SearchMisses\Pages\ListSearchMisses;
use App\Filament\Widgets\GuestConversionTrendChart;
use App\Models\AppSession;
use App\Models\RestaurantPhoto;
use App\Models\SearchMiss;
use App\Models\User;
use App\Services\Analytics\GuestConversionReport;
use App\Services\Analytics\RetentionReport;
use App\Support\Halal\HalalStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Support\BuildsHalalFixtures;
use Tests\TestCase;

class AdminAnalyticsTest extends TestCase
{
    use BuildsHalalFixtures, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-09-24 12:00:00')); // a Thursday
        $this->admin = User::factory()->create(['role' => 'superadmin', 'status' => 'active', 'created_at' => now()->subYear()]);
    }

    private function upgradedGuest(string $source, int $createdDaysAgo, int $hoursToConvert): User
    {
        $created = now()->subDays($createdDaysAgo);

        return User::factory()->create([
            'created_at' => $created,
            'upgraded_from_guest_at' => $created->copy()->addHours($hoursToConvert),
            'signup_source' => $source,
        ]);
    }

    public function test_guest_conversion_summary(): void
    {
        User::factory()->guest()->count(2)->create(['created_at' => now()->subDays(3)]);
        $this->upgradedGuest('nudge_picks', createdDaysAgo: 5, hoursToConvert: 2);
        $this->upgradedGuest('nudge_picks', createdDaysAgo: 4, hoursToConvert: 6);
        $this->upgradedGuest('settings', createdDaysAgo: 2, hoursToConvert: 10);
        User::factory()->create(['created_at' => now()->subDays(1), 'signup_source' => 'login_screen']);
        User::factory()->guest()->create(['created_at' => now()->subDays(60)]); // outside 30d

        $summary = app(GuestConversionReport::class)->summary(30);

        $this->assertSame(5, $summary['guests']);
        $this->assertSame(3, $summary['converted']);
        $this->assertEqualsWithDelta(0.6, $summary['rate'], 0.0001);
        $this->assertSame(6.0, $summary['medianHoursToConvert']);
        $this->assertSame(['source' => 'nudge_picks', 'total' => 2, 'medianHours' => 4.0], $summary['conversionsBySource'][0]);
        $this->assertSame([['source' => 'login_screen', 'total' => 1]], $summary['freshSignupsBySource']);
    }

    public function test_retention_counts_only_windows_that_have_fully_passed(): void
    {
        // Signed up 10 days ago; came back on day 1 only.
        $returning = User::factory()->create(['created_at' => now()->subDays(10)]);
        AppSession::create(['user_id' => $returning->id, 'started_at' => $returning->created_at->copy()->addDay()->addHours(3)]);
        // Same age; never came back (a session on day 0 doesn't count).
        $gone = User::factory()->create(['created_at' => now()->subDays(10)]);
        AppSession::create(['user_id' => $gone->id, 'started_at' => $gone->created_at->copy()->addMinutes(5)]);

        $cohort = collect(app(RetentionReport::class)->cohorts(4, 'registered'))
            ->firstWhere('cohort', now()->subDays(10)->startOfWeek()->toDateString());

        $this->assertSame(2, $cohort['size']);
        $this->assertSame(['eligible' => 2, 'retained' => 1, 'rate' => 0.5], $cohort['windows']['d1']);
        $this->assertSame(['eligible' => 2, 'retained' => 0, 'rate' => 0.0], $cohort['windows']['d7']);
        // Day 30 hasn't happened for them yet — no number rather than a fake 0%.
        $this->assertNull($cohort['windows']['d30']['rate']);
    }

    public function test_analytics_pages_render(): void
    {
        $this->upgradedGuest('nudge_saves', createdDaysAgo: 3, hoursToConvert: 30);
        $this->actingAs($this->admin, 'web');

        Livewire::test(GuestConversion::class)->assertOk()->assertSee('nudge_saves')->assertSee('30.0 h');
        Livewire::test(GuestConversionTrendChart::class)->assertOk();
        Livewire::test(Retention::class)->assertOk()->assertSee('Signup week')
            ->set('segment', 'guests')->assertOk();
    }

    public function test_restaurant_data_quality_tabs(): void
    {
        $withPhoto = $this->makeRestaurant(['opening_hours' => ['periods' => [['open' => ['day' => 1]]]], 'rating' => 4.5, 'user_rating_count' => 200, 'halal_status' => HalalStatus::Certified]);
        RestaurantPhoto::create(['restaurant_id' => $withPhoto->id, 'disk' => config('restaurant_photos.public_disk'), 'path' => 'p.jpg', 'is_active' => true]);
        $bare = $this->makeRestaurant(['opening_hours' => ['open_now' => true, 'periods' => null], 'rating' => null]);
        $closed = $this->makeRestaurant(['is_active' => false]);
        $this->actingAs($this->admin, 'web');

        Livewire::test(ListRestaurants::class)
            ->set('activeTab', 'no_photos')
            ->assertCanSeeTableRecords([$bare])
            ->assertCanNotSeeTableRecords([$withPhoto, $closed])
            ->set('activeTab', 'no_hours')
            ->assertCanSeeTableRecords([$bare])
            ->assertCanNotSeeTableRecords([$withPhoto, $closed])
            ->set('activeTab', 'halal_unknown')
            ->assertCanSeeTableRecords([$bare])
            ->assertCanNotSeeTableRecords([$withPhoto])
            ->set('activeTab', 'few_reviews')
            ->assertCanSeeTableRecords([$bare])
            ->assertCanNotSeeTableRecords([$withPhoto]);
    }

    public function test_search_miss_can_be_resolved_and_reopens_if_it_keeps_missing(): void
    {
        SearchMiss::record('kopi tiam', 2.93, 101.78);
        $miss = SearchMiss::firstOrFail();
        $this->actingAs($this->admin, 'web');

        Livewire::test(ListSearchMisses::class)
            ->assertCanSeeTableRecords([$miss])
            ->assertTableActionHasUrl('createPlace', RestaurantResource::getUrl('create', [
                'name' => 'kopi tiam', 'latitude' => '2.93', 'longitude' => '101.78',
            ]), $miss)
            ->callTableAction('resolve', $miss)
            ->assertCanNotSeeTableRecords([$miss]);

        $this->assertNotNull($miss->fresh()->resolved_at);
        $this->assertSame($this->admin->id, $miss->fresh()->resolved_by);

        // Same search misses again later — back on the open list.
        $this->travel(1)->hours();
        SearchMiss::record('kopi tiam', 2.93, 101.78);
        Livewire::test(ListSearchMisses::class)->assertCanSeeTableRecords([$miss->fresh()]);
    }

    public function test_create_place_form_is_prefilled_from_the_query_string(): void
    {
        $this->actingAs($this->admin, 'web');

        Livewire::withQueryParams(['name' => 'Kopi Tiam', 'latitude' => '2.93', 'longitude' => '101.78', 'provider' => 'google'])
            ->test(CreateRestaurant::class)
            ->assertFormSet(['name' => 'Kopi Tiam', 'latitude' => '2.93', 'longitude' => '101.78'])
            ->assertFormSet(['provider' => null]);
    }
}
