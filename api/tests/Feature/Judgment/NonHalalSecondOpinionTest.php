<?php

namespace Tests\Feature\Judgment;

use App\Filament\Resources\Halal\Restaurants\Pages\ListHalalRestaurants;
use App\Jobs\SecondOpinionNonHalal;
use App\Models\AiJudgment;
use App\Models\RestaurantMenuItem;
use App\Services\Halal\HalalVerificationService;
use App\Support\Halal\HalalStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\BuildsHalalFixtures;
use Tests\Support\FakesJudgments;
use Tests\TestCase;

class NonHalalSecondOpinionTest extends TestCase
{
    use BuildsHalalFixtures, FakesJudgments, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableJudgments();
    }

    private function wineBar(array $overrides = [])
    {
        $r = $this->makeRestaurant(['name' => 'The Cork Wine Bar', 'google_types' => ['wine_bar', 'restaurant'], 'food_category' => 'western', ...$overrides]);
        RestaurantMenuItem::create(['restaurant_id' => $r->id, 'name' => 'House red by the glass', 'sort_order' => 1]);

        return $r;
    }

    public function test_weak_match_with_enough_context_gets_a_hint_and_appears_in_review_tab_without_status_change(): void
    {
        Http::fake(['openrouter.ai/*' => $this->judgmentResponse(['likely_serves_pork_or_alcohol' => ['p_yes' => 0.9]])]);
        $r = $this->wineBar();

        SecondOpinionNonHalal::dispatchSync($r->id);

        $r->refresh();
        $this->assertEqualsWithDelta(0.9, $r->halal_ai_hint['probability'], 1e-6);
        $this->assertSame(3, $r->halal_ai_hint['samples']);
        $this->assertSame(HalalStatus::Unknown, $r->halal_status);
        $this->assertSame(0, $r->halalVerifications()->count());

        $this->actingAs($this->makeAdmin(), 'web');
        Livewire::test(ListHalalRestaurants::class, ['activeTab' => 'ai_flagged'])->assertCanSeeTableRecords([$r]);
    }

    public function test_thin_context_skips_the_model(): void
    {
        Http::fake();
        $r = $this->makeRestaurant(['name' => 'Wine Corner', 'google_types' => ['restaurant']]);

        SecondOpinionNonHalal::dispatchSync($r->id);

        Http::assertNothingSent();
        $this->assertNull($r->fresh()->halal_ai_hint);
    }

    public function test_strong_match_and_human_decided_places_skip_the_model(): void
    {
        Http::fake();
        $strong = $this->wineBar(['name' => 'Bak Kut Teh & Wine House']);
        $decided = $this->wineBar();
        app(HalalVerificationService::class)->recordAdminOverride($decided, HalalStatus::MuslimFriendly, $this->makeAdmin(), 'Visited, mocktails only');

        SecondOpinionNonHalal::dispatchSync($strong->id);
        SecondOpinionNonHalal::dispatchSync($decided->id);

        Http::assertNothingSent();
    }

    public function test_later_human_decision_labels_the_outcome(): void
    {
        Http::fake(['openrouter.ai/*' => $this->judgmentResponse(['likely_serves_pork_or_alcohol' => ['p_yes' => 0.9]])]);
        $r = $this->wineBar();
        SecondOpinionNonHalal::dispatchSync($r->id);

        app(HalalVerificationService::class)->recordAdminOverride($r->fresh(), HalalStatus::NonHalal, $this->makeAdmin(), 'Serves wine');

        $this->assertSame(['status' => 'non_halal'], AiJudgment::sole()->outcome);
    }

    public function test_classify_command_queues_second_opinion_for_weak_matches(): void
    {
        Http::fake(['openrouter.ai/*' => $this->judgmentResponse(['likely_serves_pork_or_alcohol' => ['p_yes' => 0.2]])]);
        $r = $this->wineBar();

        $this->artisan('halal:classify')->expectsOutputToContain('AI second opinion queued')->assertSuccessful();

        $this->assertEqualsWithDelta(0.2, $r->fresh()->halal_ai_hint['probability'], 1e-6);
    }
}
