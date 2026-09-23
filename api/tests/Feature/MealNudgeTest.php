<?php

namespace Tests\Feature;

use App\Models\AppSession;
use App\Models\Decision;
use App\Models\DeviceToken;
use App\Models\MealNudge;
use App\Models\MealNudgeState;
use App\Models\Restaurant;
use App\Models\User;
use App\Notifications\MealtimeNudge;
use App\Services\Brain\WeatherService;
use App\Services\Nudges\MealNudgeDispatcher;
use App\Services\Nudges\MealNudgeTiming;
use App\Services\Nudges\NudgeCopyCatalog;
use App\Services\Nudges\NudgePickFinder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MealNudgeTest extends TestCase
{
    use RefreshDatabase;

    private const LAT = 2.928400;

    private const LNG = 101.780200;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function local(string $dateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, 'Asia/Kuala_Lumpur');
    }

    private function optedInUser(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'role' => 'user', 'status' => 'active',
            'notification_preferences' => ['mealtime_nudges' => true],
        ], $attributes));
        DeviceToken::create(['user_id' => $user->id, 'installation_id' => 'i-'.$user->id, 'token' => 'tok-'.$user->id, 'platform' => 'ios', 'environment' => 'development']);

        return $user;
    }

    private function decisionAt(User $user, CarbonImmutable $at, float $lat = self::LAT, float $lng = self::LNG): Decision
    {
        $decision = Decision::create([
            'user_id' => $user->id, 'mode' => 'solo', 'client_token' => 'tok-'.uniqid(),
            'latitude' => $lat, 'longitude' => $lng, 'max_distance' => 2.0, 'budget_max' => 2,
        ]);
        $decision->forceFill(['created_at' => $at->utc()])->save();

        return $decision;
    }

    /** Open 10:00–22:00 every day, by weekly hours. */
    private function openRestaurant(array $overrides = []): Restaurant
    {
        return Restaurant::create(array_merge([
            'name' => 'Nasi Kandar Pelita', 'latitude' => self::LAT + 0.002, 'longitude' => self::LNG,
            'is_active' => true, 'provider' => 'google', 'provider_place_id' => 'p-'.uniqid(),
            'rating' => 4.6, 'price_level' => 1,
            'opening_hours' => [
                'utc_offset_minutes' => 480,
                'periods' => array_map(fn (int $day) => [
                    'open' => ['day' => $day, 'hour' => 10, 'minute' => 0],
                    'close' => ['day' => $day, 'hour' => 22, 'minute' => 0],
                ], range(0, 6)),
            ],
        ], $overrides));
    }

    private function dueAt(User $user, CarbonImmutable $at, string $slot = 'lunch', array $state = []): MealNudgeState
    {
        return MealNudgeState::create(array_merge(['user_id' => $user->id, 'next_nudge_at' => $at->utc(), 'next_slot' => $slot], $state));
    }

    private function dispatch(CarbonImmutable $now): array
    {
        $this->travelTo($now);

        return app(MealNudgeDispatcher::class)->dispatch($now->utc());
    }

    // ── Timing ──────────────────────────────────────────────────────────────────

    public function test_defaults_to_lunch_then_dinner_in_local_time(): void
    {
        $user = $this->optedInUser();
        $timing = app(MealNudgeTiming::class);

        $this->assertEquals($this->local('2026-09-23 12:00')->utc(), $timing->next($user, $this->local('2026-09-23 09:00'))['at']);
        $this->assertSame('dinner', $timing->next($user, $this->local('2026-09-23 12:30'))['slot']);
    }

    public function test_personal_timing_is_the_median_decision_minute_minus_the_lead(): void
    {
        $user = $this->optedInUser();
        foreach (['12:40', '12:50', '13:00'] as $index => $time) {
            $this->decisionAt($user, $this->local("2026-09-2{$index} {$time}"));
        }

        $next = app(MealNudgeTiming::class)->next($user, $this->local('2026-09-23 09:00'));

        $this->assertEquals($this->local('2026-09-23 12:35')->utc(), $next['at']);
    }

    public function test_friday_lunch_waits_for_jumaat(): void
    {
        $next = app(MealNudgeTiming::class)->next($this->optedInUser(), $this->local('2026-09-25 09:00'));

        $this->assertEquals($this->local('2026-09-25 14:15')->utc(), $next['at']);
    }

    public function test_ramadan_replaces_lunch_with_a_pre_iftar_nudge_for_halal_users(): void
    {
        config(['brain.context.ramadan' => [['2026-09-20', '2026-09-30']]]);

        $next = app(MealNudgeTiming::class)->next($this->optedInUser(['halal_preference' => true]), $this->local('2026-09-23 09:00'));

        $this->assertSame('iftar', $next['slot']);
        $this->assertEquals($this->local('2026-09-23 17:45')->utc(), $next['at']);
    }

    public function test_never_schedules_inside_quiet_hours(): void
    {
        config(['nudges.default_times.dinner' => '22:30']);

        $next = app(MealNudgeTiming::class)->next($this->optedInUser(), $this->local('2026-09-23 13:00'));

        $this->assertEquals($this->local('2026-09-24 12:00')->utc(), $next['at']);
    }

    // ── Dispatch ────────────────────────────────────────────────────────────────

    public function test_sends_a_named_nudge_for_a_close_open_good_place_and_schedules_the_next(): void
    {
        $user = $this->optedInUser();
        $restaurant = $this->openRestaurant();
        $this->decisionAt($user, $this->local('2026-09-22 13:00'));
        $this->dueAt($user, $this->local('2026-09-23 12:00'));

        $results = $this->dispatch($this->local('2026-09-23 12:01'));

        $this->assertSame('sent', $results[0]['outcome']);
        $nudge = MealNudge::sole();
        $this->assertSame($restaurant->id, $nudge->restaurant_id);
        Notification::assertSentTo($user, MealtimeNudge::class, function (MealtimeNudge $notification) use ($user, $nudge) {
            $payload = $notification->toApn($user);

            return str_contains($payload->body, 'Nasi Kandar Pelita') && $payload->custom['nudgeId'] === $nudge->id;
        });
        // One a day: today's nudge is spent, so the next one is tomorrow's lunch.
        $this->assertEquals($this->local('2026-09-24 12:00')->utc(), MealNudgeState::sole()->next_nudge_at);
    }

    public function test_stale_location_or_no_good_open_place_sends_a_generic_nudge(): void
    {
        $user = $this->optedInUser();
        $this->openRestaurant(['name' => 'Closed Kedai', 'opening_hours' => ['open_now' => false, 'checked_at' => now()->toIso8601String()]]);
        $this->openRestaurant(['name' => 'Unknown Hours', 'opening_hours' => null]);
        $this->decisionAt($user, $this->local('2026-09-22 13:00'));
        $this->dueAt($user, $this->local('2026-09-23 12:00'));

        $this->dispatch($this->local('2026-09-23 12:01'));

        $this->assertNull(MealNudge::sole()->restaurant_id);
        $this->assertStringStartsWith('generic_', MealNudge::sole()->copy_key);
    }

    public function test_location_older_than_three_days_is_never_named(): void
    {
        $user = $this->optedInUser();
        $this->openRestaurant();
        $this->decisionAt($user, $this->local('2026-09-18 13:00'));

        $found = app(NudgePickFinder::class)->find($user, $this->local('2026-09-23 12:00')->utc());

        $this->assertNull($found['pick']);
    }

    public function test_finding_a_pick_never_calls_google(): void
    {
        config(['services.places.provider' => 'google', 'services.places.google_api_key' => 'fake-key']);
        Http::fake();
        $user = $this->optedInUser();
        $this->decisionAt($user, $this->local('2026-09-22 13:00'));

        app(NudgePickFinder::class)->find($user, $this->local('2026-09-23 12:00')->utc());

        Http::assertNothingSent();
    }

    public function test_old_rain_readings_never_make_rain_copy(): void
    {
        $this->mock(WeatherService::class)->shouldReceive('current')->andReturn(['raining' => true, 'ageMinutes' => 120, 'confidence' => 0.3]);
        $user = $this->optedInUser();
        $this->decisionAt($user, $this->local('2026-09-22 13:00'));

        $this->assertFalse(app(NudgePickFinder::class)->find($user, $this->local('2026-09-23 12:00')->utc())['raining']);
    }

    public function test_opted_out_or_recently_active_users_are_skipped(): void
    {
        $optedOut = $this->optedInUser(['notification_preferences' => ['mealtime_nudges' => false]]);
        $active = $this->optedInUser();
        AppSession::create(['user_id' => $active->id, 'started_at' => $this->local('2026-09-23 11:40')->utc()]);
        $this->dueAt($optedOut, $this->local('2026-09-23 12:00'));
        $this->dueAt($active, $this->local('2026-09-23 12:00'));

        $results = collect($this->dispatch($this->local('2026-09-23 12:01')))->keyBy('user_id');

        $this->assertSame('opted_out', $results[$optedOut->id]['detail']);
        $this->assertSame('recently_active', $results[$active->id]['detail']);
        Notification::assertNothingSent();
        $this->assertSame(0, MealNudge::count());
    }

    public function test_at_most_one_nudge_per_local_day(): void
    {
        $user = $this->optedInUser();
        $this->dueAt($user, $this->local('2026-09-23 12:00'));
        $this->dispatch($this->local('2026-09-23 12:01'));

        MealNudgeState::sole()->update(['next_nudge_at' => $this->local('2026-09-23 19:00')->utc()]);
        $results = $this->dispatch($this->local('2026-09-23 19:01'));

        $this->assertSame('already_today', $results[0]['detail']);
        $this->assertSame(1, MealNudge::count());
    }

    public function test_three_unopened_nudges_pause_and_three_pauses_stop(): void
    {
        $user = $this->optedInUser();
        MealNudge::create(['user_id' => $user->id, 'local_date' => '2026-09-22', 'slot' => 'lunch', 'copy_key' => 'generic_lunch', 'status' => 'sent', 'sent_at' => $this->local('2026-09-22 12:00')->utc()]);
        $this->dueAt($user, $this->local('2026-09-23 12:00'), state: ['unopened_streak' => 2]);

        $this->assertSame('backoff', $this->dispatch($this->local('2026-09-23 12:01'))[0]['detail']);
        $state = MealNudgeState::sole();
        $this->assertSame(1, $state->pause_count);
        $this->assertTrue($state->paused_until->isFuture());

        $state->update(['pause_count' => 2, 'unopened_streak' => 2, 'paused_until' => null, 'next_nudge_at' => $this->local('2026-09-23 19:00')->utc()]);
        $this->dispatch($this->local('2026-09-23 19:01'));
        $this->assertNotNull(MealNudgeState::sole()->stopped_at);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $user = $this->optedInUser();
        $this->dueAt($user, $this->local('2026-09-23 12:00'));
        $this->travelTo($this->local('2026-09-23 12:01'));

        $this->artisan('nudges:dispatch', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, MealNudge::count());
        Notification::assertNothingSent();
    }

    // ── Opt-in + funnel ─────────────────────────────────────────────────────────

    public function test_turning_mealtime_picks_on_schedules_a_clean_slate(): void
    {
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);
        MealNudgeState::create(['user_id' => $user->id, 'pause_count' => 3, 'stopped_at' => now()]);
        Sanctum::actingAs($user, ['*']);

        $this->patchJson('/api/v1/me/notification-preferences', ['mealtime_nudges' => true])->assertOk()
            ->assertJsonPath('preferences.mealtime_nudges', true);

        $state = MealNudgeState::sole();
        $this->assertNull($state->stopped_at);
        $this->assertSame(0, $state->pause_count);
        $this->assertNotNull($state->next_nudge_at);
    }

    public function test_funnel_events_are_owner_only_idempotent_and_reset_the_streak(): void
    {
        $user = $this->optedInUser();
        MealNudgeState::create(['user_id' => $user->id, 'unopened_streak' => 2]);
        $nudge = MealNudge::create(['user_id' => $user->id, 'local_date' => '2026-09-23', 'slot' => 'lunch', 'copy_key' => 'generic_lunch', 'status' => 'sent', 'sent_at' => now()]);

        Sanctum::actingAs($this->optedInUser(), ['*']);
        $this->postJson("/api/v1/me/nudges/{$nudge->id}/events", ['event' => 'opened'])->assertNotFound();

        Sanctum::actingAs($user, ['*']);
        $this->postJson("/api/v1/me/nudges/{$nudge->id}/events", ['event' => 'quick_pick_started'])->assertOk();
        $this->postJson("/api/v1/me/nudges/{$nudge->id}/events", ['event' => 'makan_sini'])->assertOk();

        $nudge->refresh();
        $this->assertNotNull($nudge->opened_at);
        $this->assertSame('quick_pick_started', $nudge->action);
        $this->assertSame(0, MealNudgeState::sole()->unopened_streak);
    }

    public function test_copy_is_deterministic_and_never_mentions_halal(): void
    {
        $day = $this->local('2026-09-23 00:00');
        $place = ['name' => 'Nasi Kandar Pelita', 'distanceKm' => 0.4, 'closesAt' => '10:00 PM'];

        $first = NudgeCopyCatalog::compose('lunch', $place, false, $day, 7);
        $this->assertSame($first, NudgeCopyCatalog::compose('lunch', $place, false, $day, 7));
        $this->assertStringContainsString('400 m', $first['body']);
        $this->assertSame('rain_lunch', NudgeCopyCatalog::compose('lunch', $place, true, $day, 7)['key']);
        $this->assertSame('generic_dinner', NudgeCopyCatalog::compose('dinner', null, true, $day, 7)['key']);
        $this->assertSame('friday_lunch', NudgeCopyCatalog::compose('lunch', $place, false, $this->local('2026-09-25 00:00'), 7)['key']);

        foreach (NudgeCopyCatalog::keys() as $key) {
            foreach ([['lunch', $place], ['dinner', null], ['iftar', $place]] as [$slot, $p]) {
                $copy = NudgeCopyCatalog::compose($slot, $p, str_starts_with($key, 'rain'), $day, 7);
                $this->assertStringNotContainsStringIgnoringCase('halal', $copy['title'].$copy['body']);
            }
        }
    }
}
