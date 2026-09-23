<?php

namespace Tests\Feature;

use App\Filament\Pages\MakanBrainInsights;
use App\Models\Decision;
use App\Models\DecisionInteraction;
use App\Models\DecisionRecommendation;
use App\Models\Restaurant;
use App\Models\TasteEvent;
use App\Models\TasteProfile;
use App\Models\User;
use App\Services\Brain\BrainEvaluationReport;
use App\Services\Brain\TasteOwner;
use App\Services\Brain\TasteProfileBuilder;
use Database\Seeders\RestaurantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class MakanBrainTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('brain.enabled', true);
        Http::preventStrayRequests();
        $this->seed(RestaurantSeeder::class);
        $this->user = User::factory()->create(['role' => 'user', 'status' => 'active']);
        Sanctum::actingAs($this->user, ['*']);
    }

    private function decide(array $overrides = []): array
    {
        return $this->postJson('/api/v1/recommendations/solo', array_merge([
            'latitude' => 2.928400,
            'longitude' => 101.780200,
            'budgetMax' => 3,
            'maxDistanceKm' => 5.0,
            'moods' => [],
            'installationId' => 'install-1',
        ], $overrides))->assertOk()->json();
    }

    private function onDecision(string $method, array $decision, string $path, array $body = [])
    {
        return $this->json($method, "/api/v1/decisions/{$decision['decisionId']}/{$path}", $body, ['X-Decision-Token' => $decision['clientToken']]);
    }

    public function test_solo_returns_brain_payload_and_persists_the_decision_trace(): void
    {
        $decision = $this->decide();

        $this->assertSame('v2', $decision['algorithmVersion']);
        $rec = $decision['recommendation'];
        $this->assertContains($rec['fit'], ['strong', 'good', 'wildcard']);
        $this->assertNotEmpty($rec['thinkingTrace']);
        $this->assertSame('Picked this one', end($rec['thinkingTrace']));
        $this->assertLessThanOrEqual(3, count($rec['reasons']));
        $this->assertSame(count($rec['reasons']), count(array_unique(array_column($rec['reasons'], 'family'))));

        $row = Decision::findOrFail($decision['decisionId']);
        $this->assertSame('v2', $row->algorithm_version);
        $this->assertNotNull($row->session_id);
        $this->assertNotNull($row->funnel);
        $this->assertNotNull($row->context_snapshot);
        $this->assertSame('anything', $row->intent_type);

        $candidates = DecisionRecommendation::where('decision_id', $row->id)->orderBy('score_rank')->get();
        $this->assertLessThanOrEqual(8, $candidates->count());
        $this->assertSame(1, $candidates->where('selected', true)->count());
        $this->assertEqualsWithDelta(1.0, $candidates->sum('selection_probability'), 0.01);
        $this->assertNotNull($candidates->first()->breakdown['components']['distance']);
        $this->assertNotNull($candidates->first()->reason_facts);
    }

    public function test_kill_switch_gives_the_v1_response(): void
    {
        Config::set('brain.enabled', false);

        $decision = $this->decide();

        $this->assertSame('v1', $decision['algorithmVersion']);
        $this->assertArrayNotHasKey('reasons', $decision['recommendation']);
        $this->assertSame('v1', Decision::findOrFail($decision['decisionId'])->algorithm_version);
    }

    public function test_explicit_craving_outranks_context_and_skips_diversity(): void
    {
        Config::set('brain.context.overlay.month_end', ['cheapEatsFit' => 50]);
        $this->travelTo(now()->setDate(2026, 9, 20)->setTime(4, 0));

        $decision = $this->decide(['craving' => 'nasi kandar', 'budgetMax' => null]);

        $this->assertTrue($decision['craving']['matched']);
        $this->assertSame(2, DecisionRecommendation::where('decision_id', $decision['decisionId'])->where('selected', true)->first()->breakdown['tier']);
        $this->assertSame(0, Decision::find($decision['decisionId'])->funnel['diversified']);
    }

    public function test_accept_teaches_selera_and_retries_do_not_teach_twice(): void
    {
        $decision = $this->decide();

        $this->onDecision('POST', $decision, 'accept')->assertOk();
        $count = TasteEvent::count();
        $this->assertGreaterThan(0, $count);
        $this->assertSame(1, TasteProfile::where('user_id', $this->user->id)->value('signal_count'));

        $this->onDecision('POST', $decision, 'accept')->assertOk();
        $this->assertSame($count, TasteEvent::count());
    }

    public function test_reroll_and_why_not_feed_moment_pulse_idempotently(): void
    {
        $decision = $this->decide();

        $next = $this->onDecision('POST', $decision, 'reroll')->assertOk()->json('recommendation');
        if ($next === null) {
            $this->markTestSkipped('Fixture pool too small to reroll.');
        }
        $this->assertArrayHasKey('reasons', $next);
        $this->assertTrue(TasteEvent::where('signal', 'reroll')->exists());

        $this->onDecision('POST', $decision, 'why-not', ['reason' => 'not_feeling_it', 'detail' => 'just_not_today'])->assertOk();
        $afterFirst = TasteEvent::where('signal', 'why_not')->count();
        $this->onDecision('POST', $decision, 'why-not', ['reason' => 'not_feeling_it', 'detail' => 'just_not_today'])->assertOk();

        $this->assertSame($afterFirst, TasteEvent::where('signal', 'why_not')->count());
        // just_not_today is today-only — nothing reaches long-term Selera.
        $this->assertSame(0, TasteEvent::where('signal', 'why_not')->where('scope', '!=', 'pulse')->count());
        $this->assertSame([], TasteProfile::where('user_id', $this->user->id)->first()->memory['longTerm']);
    }

    public function test_why_not_and_tune_require_the_decision_token(): void
    {
        $decision = $this->decide();

        $this->postJson("/api/v1/decisions/{$decision['decisionId']}/why-not", ['reason' => 'too_far'])->assertForbidden();
        $this->postJson("/api/v1/decisions/{$decision['decisionId']}/tune", ['direction' => 'closer'])->assertForbidden();
    }

    public function test_decision_fatigue_after_five_actions_gives_the_safest_bet(): void
    {
        $decision = $this->decide();
        $last = null;
        for ($i = 0; $i < 5; $i++) {
            $last = $this->onDecision('POST', $decision, 'reroll')->assertOk()->json('recommendation');
            if ($last === null) {
                $this->markTestSkipped('Fixture pool ran out before fatigue.');
            }
        }

        $this->assertTrue($last['fatigue']);
        $this->assertStringContainsString('enough choosing', $last['reasons'][0]['text']);
        $this->assertTrue(Decision::find($decision['decisionId'])->fatigue_mode);
    }

    public function test_tune_composes_and_caps_into_search_wider(): void
    {
        $decision = $this->decide(['maxDistanceKm' => 5.0]);
        $shownBefore = DecisionRecommendation::where('decision_id', $decision['decisionId'])->whereNotNull('shown_at')->first();

        $first = $this->onDecision('POST', $decision, 'tune', ['direction' => 'closer'])->assertOk()->json();
        if ($first['recommendation'] !== null) {
            $this->assertLessThan($shownBefore->breakdown['facts']['distanceKm'], DecisionRecommendation::where('decision_id', $decision['decisionId'])->where('restaurant_id', $first['recommendation']['id'])->first()->breakdown['facts']['distanceKm']);
            $this->assertStringStartsWith('Closer', $first['recommendation']['reasons'][0]['text']);
            $this->onDecision('POST', $decision, 'tune', ['direction' => 'safer'])->assertOk();
        }

        // Past the cap (or nothing better): the escape hatch, never a silent null.
        Decision::find($decision['decisionId'])->update(['tunes' => ['closer', 'safer']]);
        $capped = $this->onDecision('POST', $decision, 'tune', ['direction' => 'cheaper'])->assertOk()->json();
        $this->assertNull($capped['recommendation']);
        $this->assertTrue($capped['canSearchWider']);
        $this->assertEquals(9.0, $capped['suggestedAdjustment']['distanceKm']); // 5 km × 1.75, rounded to the half km
    }

    public function test_what_if_lists_only_winner_flipping_components_and_choose_swaps(): void
    {
        $decision = $this->decide();

        $entries = $this->onDecision('GET', $decision, 'what-if')->assertOk()->json('whatIf');
        $this->assertLessThanOrEqual(3, count($entries));
        foreach ($entries as $entry) {
            $this->assertNotSame($decision['recommendation']['id'], $entry['winner']['id']);
        }
        $this->assertDatabaseHas('decision_interactions', ['decision_id' => $decision['decisionId'], 'type' => 'what_if_opened']);

        if ($entries) {
            $this->onDecision('POST', $decision, 'choose', ['restaurantId' => $entries[0]['winner']['id']])
                ->assertOk()->assertJsonPath('recommendation.id', $entries[0]['winner']['id']);
        }
    }

    public function test_interactions_are_idempotent_and_validated(): void
    {
        $decision = $this->decide();

        $this->onDecision('POST', $decision, 'interactions', ['type' => 'directions_opened'])->assertOk();
        $this->onDecision('POST', $decision, 'interactions', ['type' => 'directions_opened'])->assertOk();
        $this->onDecision('POST', $decision, 'interactions', ['type' => 'nope'])->assertUnprocessable();

        $this->assertSame(1, DecisionInteraction::count());
    }

    public function test_lens_is_validated_and_recorded(): void
    {
        $this->postJson('/api/v1/recommendations/solo', ['latitude' => 2.9284, 'longitude' => 101.7802, 'maxDistanceKm' => 5, 'lens' => 'broke'])->assertUnprocessable();

        $decision = $this->decide(['lens' => 'quick_one', 'budgetMax' => null]);

        $this->assertSame('quick_one', Decision::find($decision['decisionId'])->lens);
        $this->assertSame('lens_quick_one', collect($decision['recommendation']['reasons'])->firstWhere('family', 'match')['key']);
    }

    public function test_selera_shows_traits_with_evidence_and_constraints_separately(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $decision = $this->decide();
            $this->onDecision('POST', $decision, 'accept')->assertOk();
        }

        $selera = $this->getJson('/api/v1/me/selera')->assertOk()->json();

        $this->assertSame('learning', $selera['stage']);
        $this->assertSame(4, $selera['signalCount']);
        $this->assertContains('halal', array_column($selera['constraints'], 'key'));
        $this->assertNotContains('halal', array_map(fn ($t) => explode(':', $t['key'])[0], $selera['traits']));
        foreach ($selera['traits'] as $trait) {
            $this->assertNotEmpty($trait['evidence']);
        }
    }

    public function test_trait_correction_mute_and_reset_keep_history(): void
    {
        $decision = $this->decide();
        $this->onDecision('POST', $decision, 'accept')->assertOk();

        $this->postJson('/api/v1/me/selera/traits/category:cafe/feedback', ['kind' => 'not_really'])->assertOk();
        $this->deleteJson('/api/v1/me/selera/traits/price:1')->assertOk();
        $profile = TasteProfile::where('user_id', $this->user->id)->first();
        $this->assertSame('not_really', $profile->corrections['category:cafe']);
        $this->assertContains('price:1', $profile->muted);

        $events = TasteEvent::count();
        $this->postJson('/api/v1/me/selera/reset')->assertOk()->assertJsonPath('signalCount', 0);

        $this->assertSame($events + 1, TasteEvent::count()); // reset is a boundary, nothing deleted
        $this->assertSame([], TasteProfile::where('user_id', $this->user->id)->first()->memory['longTerm']);
    }

    public function test_rebuild_reproduces_the_incremental_profile_exactly(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $decision = $this->decide();
            $this->onDecision('POST', $decision, 'reroll');
            $this->onDecision('POST', $decision, 'why-not', ['reason' => 'too_far']);
            $this->onDecision('POST', $decision, 'accept');
        }
        $live = TasteProfile::where('user_id', $this->user->id)->first()->only(['memory', 'muted', 'corrections', 'signal_count']);

        $this->artisan('brain:rebuild-taste', ['--user' => $this->user->id])->assertSuccessful();
        $rebuilt = TasteProfile::where('user_id', $this->user->id)->first()->only(['memory', 'muted', 'corrections', 'signal_count']);
        app(TasteProfileBuilder::class)->rebuild(new TasteOwner($this->user->id, null));
        $again = TasteProfile::where('user_id', $this->user->id)->first()->only(['memory', 'muted', 'corrections', 'signal_count']);

        $this->assertEquals($live, $rebuilt);
        $this->assertEquals($rebuilt, $again);
    }

    public function test_anonymous_installation_learning_merges_into_the_user_on_first_signed_in_decision(): void
    {
        $anon = User::factory()->make(['role' => 'user', 'status' => 'active']);
        Sanctum::actingAs($anon, ['*']);
        $decision = $this->decide(['installationId' => 'install-anon']);
        $this->onDecision('POST', $decision, 'accept')->assertOk();
        $this->assertTrue(TasteProfile::whereNull('user_id')->where('installation_id', 'install-anon')->exists());

        Sanctum::actingAs($this->user, ['*']);
        $this->decide(['installationId' => 'install-anon']);

        $this->assertFalse(TasteProfile::whereNull('user_id')->where('installation_id', 'install-anon')->exists());
        $this->assertSame(1, TasteProfile::where('user_id', $this->user->id)->value('signal_count'));
    }

    public function test_context_endpoint_lists_signals_for_the_strip(): void
    {
        $this->travelTo(now()->setTimezone('Asia/Kuala_Lumpur')->setTime(23, 30)->utc());

        $this->getJson('/api/v1/context?latitude=3.139&longitude=101.687')
            ->assertOk()->assertJsonPath('mealSlot', 'supper')->assertJsonPath('signals.0.key', 'supper');
        $this->getJson('/api/v1/context?latitude=3.139&longitude=101.687&ignore[]=supper')
            ->assertOk()->assertJsonPath('signals.0.ignored', true);
    }

    public function test_evaluation_page_renders_every_cohort(): void
    {
        $decision = $this->decide();
        $this->onDecision('POST', $decision, 'accept')->assertOk();
        Config::set('brain.enabled', false);
        $this->decide();

        $this->actingAs(User::factory()->create(['role' => 'superadmin', 'status' => 'active']), 'web');
        foreach (array_keys(BrainEvaluationReport::COHORTS) as $cohort) {
            Livewire::withQueryParams(['cohort' => $cohort])->test(MakanBrainInsights::class)->assertOk();
        }
        Livewire::test(MakanBrainInsights::class)->assertSee('v2')->assertSee('v1');
    }

    public function test_nearby_pick_uses_the_brain_too(): void
    {
        $this->postJson('/api/v1/places/nearby/pick', [
            'latitude' => 2.9284, 'longitude' => 101.7802,
            'viewport' => ['north' => 2.95, 'south' => 2.90, 'east' => 101.80, 'west' => 101.76],
            'visiblePlaceIds' => Restaurant::pluck('id')->all(),
            'installationId' => 'install-1',
        ])->assertOk()->assertJsonPath('algorithmVersion', 'v2');
    }
}
