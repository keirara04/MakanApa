<?php

namespace Tests\Feature;

use App\Models\TasteEvent;
use App\Models\TasteProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Onboarding's "What are you usually craving?" picks become weak, long-term starting hints for
 * Makan Brain — never counted as picks, never enough on their own to claim a Selera trait.
 */
class OnboardingSeedTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_picks_seed_weak_hints_without_counting_as_picks(): void
    {
        $this->postJson('/api/v1/me/selera/seed', ['picks' => ['mamak', 'korean', 'cheap_eats']])
            ->assertOk()
            ->assertJsonPath('startingPicks', ['Mamak', 'Korean', 'Cheap eats'])
            ->assertJsonPath('signalCount', 0)
            ->assertJsonPath('traits', []);

        $memory = TasteProfile::query()->where('user_id', $this->user->id)->sole()->memory;
        $this->assertEqualsWithDelta(0.2, $memory['longTerm']['category']['mamak']['v'], 0.0001);
        $this->assertEqualsWithDelta(0.1, $memory['longTerm']['cuisine']['indian']['v'], 0.0001);
        $this->assertEqualsWithDelta(0.2, $memory['longTerm']['cuisine']['korean']['v'], 0.0001);
        $this->assertEqualsWithDelta(0.2, $memory['longTerm']['price']['1']['v'], 0.0001);
        $this->assertSame(1, $memory['longTerm']['category']['mamak']['n']);

        $this->assertSame(4, TasteEvent::query()->where('user_id', $this->user->id)->where('signal', 'onboarding')->count());
    }

    public function test_seeding_happens_once_per_account(): void
    {
        $this->postJson('/api/v1/me/selera/seed', ['picks' => ['cafe']])->assertOk();
        $this->postJson('/api/v1/me/selera/seed', ['picks' => ['dessert', 'late_night']])
            ->assertOk()
            ->assertJsonPath('startingPicks', ['Cafe']);

        $this->assertSame(1, TasteEvent::query()->where('signal', 'onboarding')->count());
    }

    public function test_anything_is_remembered_without_leaning_anywhere(): void
    {
        $this->postJson('/api/v1/me/selera/seed', ['picks' => ['anything']])
            ->assertOk()
            ->assertJsonPath('startingPicks', ['Anything']);

        $this->assertSame([], TasteProfile::query()->where('user_id', $this->user->id)->sole()->memory['longTerm']);
    }

    public function test_reset_forgets_the_starting_picks(): void
    {
        $this->postJson('/api/v1/me/selera/seed', ['picks' => ['malay']])->assertOk();

        $this->postJson('/api/v1/me/selera/reset')
            ->assertOk()
            ->assertJsonPath('startingPicks', []);
    }

    public function test_unknown_or_too_many_picks_are_rejected(): void
    {
        $this->postJson('/api/v1/me/selera/seed', ['picks' => ['sushi']])->assertUnprocessable();
        $this->postJson('/api/v1/me/selera/seed', ['picks' => ['mamak', 'cafe', 'korean', 'malay', 'dessert']])->assertUnprocessable();
        $this->postJson('/api/v1/me/selera/seed', ['picks' => []])->assertUnprocessable();

        $this->assertSame(0, TasteEvent::query()->count());
    }

    public function test_requires_an_account(): void
    {
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/v1/me/selera/seed', ['picks' => ['cafe']], ['Authorization' => ''])->assertUnauthorized();
    }
}
