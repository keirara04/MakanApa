<?php

namespace Tests\Feature;

use App\Filament\Pages\ApiCosts;
use App\Filament\Widgets\ApiUsageChart;
use App\Models\AiJudgment;
use App\Models\ApiUsageDaily;
use App\Models\User;
use App\Notifications\ApiBudgetThresholdReached;
use App\Services\Analytics\ApiCostReport;
use App\Services\Places\GooglePlacesProvider;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ApiUsageTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.places.google_api_key' => 'fake-key',
            'admin_budgets.google_places.monthly_budget_usd' => 100,
            'admin_budgets.openrouter.monthly_budget_usd' => 20,
        ]);
        $this->travelTo(Carbon::parse('2026-09-15 12:00:00'));
    }

    private function place(string $id): array
    {
        return ['id' => $id, 'displayName' => ['text' => 'Kedai '.$id], 'location' => ['latitude' => 2.9, 'longitude' => 101.7], 'types' => ['restaurant']];
    }

    private function calls(string $endpoint): int
    {
        return (int) ApiUsageDaily::where('provider', 'google_places')->where('endpoint', $endpoint)->sum('calls');
    }

    private function seedUsage(string $endpoint, int $calls, string $date = '2026-09-10'): void
    {
        ApiUsageDaily::create(['date' => $date, 'provider' => 'google_places', 'endpoint' => $endpoint, 'calls' => $calls]);
    }

    private function seedJudgment(string $model, int $input, int $output): void
    {
        AiJudgment::create([
            'run_id' => (string) Str::uuid(), 'purpose' => 'craving', 'definition_version' => 1, 'provider' => 'openrouter',
            'model' => $model, 'structured_mode' => 'json_schema', 'temperature' => 0, 'samples' => 1, 'state_hash' => str_repeat('a', 64),
            'questions' => [], 'input_tokens' => $input, 'output_tokens' => $output, 'status' => 'ok',
        ]);
    }

    public function test_every_google_places_call_is_counted_per_sku(): void
    {
        Http::fake([
            'places.googleapis.com/v1/places:searchNearby' => Http::response(['places' => [$this->place('a')]]),
            'places.googleapis.com/v1/places:searchText' => Http::response(['places' => [$this->place('b')]]),
            'places.googleapis.com/v1/places/*' => Http::response(['photos' => [], 'reviews' => []]),
        ]);
        $provider = new GooglePlacesProvider('fake-key');

        $provider->nearbyRestaurants(2.9, 101.7, 1.0);
        $provider->nearbyRestaurantsBatch([
            ['lat' => 2.9, 'lon' => 101.7, 'radius' => 1.0],
            ['lat' => 2.91, 'lon' => 101.71, 'radius' => 1.0],
            ['lat' => 2.92, 'lon' => 101.72, 'radius' => 1.0],
        ]);
        $provider->searchText('nasi lemak', 2.9, 101.7, 2.0);
        $provider->searchTextBatch([['query' => 'kopi', 'includedType' => null], ['query' => 'roti', 'includedType' => 'restaurant']], 2.9, 101.7, 2.0);
        $provider->fetchPresentationDetails('ChIJ-kfc');

        $this->assertSame(4, $this->calls('nearby_search'));
        $this->assertSame(3, $this->calls('text_search'));
        $this->assertSame(1, $this->calls('place_details_atmosphere'));
        // Same-day calls share one row per SKU.
        $this->assertSame(1, ApiUsageDaily::where('endpoint', 'nearby_search')->count());
    }

    public function test_a_google_error_response_is_still_counted_and_still_thrown(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response(['error' => 'quota'], 429)]);

        try {
            (new GooglePlacesProvider('fake-key'))->searchText('nasi lemak', 2.9, 101.7, 2.0);
            $this->fail('A 429 must still surface to the caller.');
        } catch (RequestException) {
        }

        $this->assertSame(1, $this->calls('text_search'));
    }

    public function test_a_failing_counter_never_breaks_the_places_call(): void
    {
        Exceptions::fake();
        Http::fake(['places.googleapis.com/*' => Http::response(['places' => [$this->place('a')]])]);
        Schema::drop('api_usage_daily');

        $places = (new GooglePlacesProvider('fake-key'))->nearbyRestaurants(2.9, 101.7, 1.0);

        $this->assertCount(1, $places);
        Exceptions::assertReported(fn (QueryException $e) => str_contains($e->getMessage(), 'api_usage_daily'));
        // The failed write ran in its own savepoint, so the surrounding transaction still works.
        $this->assertSame(0, User::count());
    }

    public function test_photo_proxy_counts_place_photo_calls(): void
    {
        Http::fake(['*/media*' => Http::response(['photoUri' => 'https://lh3.googleusercontent.com/p/photo=w800'])]);

        $this->get(URL::temporarySignedRoute('places.photo', now()->addMinutes(30), ['name' => 'places/abc/photos/xyz']))
            ->assertRedirect();

        $this->assertSame(1, $this->calls('place_photo'));
    }

    public function test_month_to_date_cost_subtracts_free_tier_and_projects_to_month_end(): void
    {
        $this->seedUsage('nearby_search', 3000);
        $this->seedUsage('place_photo', 500);
        $this->seedUsage('nearby_search', 9999, '2026-08-31'); // last month — ignored
        $this->seedJudgment('anthropic/claude-haiku-4.5', 1_000_000, 200_000);
        $this->seedJudgment('cohere/north-mini-code:free', 5_000_000, 5_000_000);

        $report = app(ApiCostReport::class)->monthToDate();

        $google = $report['google_places'];
        // (3000 − 1000 free) × $35/1000 = $70; photos stay inside the free 1000.
        $this->assertSame(70.0, $google['cost']);
        $this->assertEqualsWithDelta(0.7, $google['percent'], 0.0001);
        // 15th of a 30-day month → ×2 usage: (6000 − 1000) × 35/1000 = $175.
        $this->assertSame(175.0, $google['projected']);

        $openRouter = $report['openrouter'];
        // 1M input × $1 + 0.2M output × $5 = $2; the :free model costs nothing.
        $this->assertSame(2.0, $openRouter['cost']);
        $this->assertSame(4.0, $openRouter['projected']);
    }

    public function test_budget_alert_fires_once_per_threshold_and_only_the_highest_when_several_cross(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'superadmin', 'status' => 'active']);
        User::factory()->create(['role' => 'superadmin', 'status' => 'suspended']);

        // $70 of $100 — under 80%.
        $this->seedUsage('nearby_search', 3000);
        $this->artisan('admin:check-api-budget')->assertSuccessful();
        Notification::assertNothingSentTo($admin, ApiBudgetThresholdReached::class);

        // $87.50 — crosses 80%.
        $this->seedUsage('text_search', 1500);
        $this->artisan('admin:check-api-budget')->assertSuccessful();
        $this->artisan('admin:check-api-budget')->assertSuccessful();
        Notification::assertSentToTimes($admin, ApiBudgetThresholdReached::class, 1);
        // The Filament bell gets its own database notification alongside the email.
        Notification::assertSentToTimes($admin, DatabaseNotification::class, 1);

        // Past 100% — one more alert.
        $this->seedUsage('place_details', 2000);
        $this->artisan('admin:check-api-budget')->assertSuccessful();
        Notification::assertSentToTimes($admin, ApiBudgetThresholdReached::class, 2);
    }

    public function test_jumping_straight_past_both_thresholds_sends_one_alert(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'superadmin', 'status' => 'active']);
        $this->seedUsage('nearby_search', 5000); // $140 of $100

        $this->artisan('admin:check-api-budget')->assertSuccessful();
        $this->artisan('admin:check-api-budget')->assertSuccessful();

        Notification::assertSentToTimes($admin, ApiBudgetThresholdReached::class, 1);
    }

    public function test_api_costs_page_and_chart_render(): void
    {
        $this->seedUsage('nearby_search', 3000, '2026-09-14');
        $this->seedJudgment('anthropic/claude-haiku-4.5', 1000, 100);
        $this->actingAs(User::factory()->create(['role' => 'superadmin', 'status' => 'active']), 'web');

        Livewire::test(ApiCosts::class)
            ->assertOk()
            ->assertSee('Google Places')
            ->assertSee('Nearby Search (Enterprise)')
            ->assertSee('$70.00');
        Livewire::test(ApiUsageChart::class)->assertOk();
    }
}
