<?php

namespace Tests\Feature\Judgment;

use App\Filament\Widgets\SystemHealthWidget;
use App\Models\AiJudgment;
use App\Models\User;
use App\Services\Craving\CravingIntent;
use App\Services\Craving\JudgmentIntentParser;
use App\Services\Judgment\JudgmentEngine;
use App\Support\FoodConceptKind;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\FakesJudgments;
use Tests\TestCase;

class JudgmentOpsTest extends TestCase
{
    use FakesJudgments, RefreshDatabase;

    private function parser(): JudgmentIntentParser
    {
        $this->enableJudgments();

        return new JudgmentIntentParser($this->app->make(JudgmentEngine::class));
    }

    public function test_craving_choice_maps_to_taxonomy_concept(): void
    {
        Http::fake(['openrouter.ai/*' => $this->judgmentResponse($this->cravingAnswer('nasi_kandar', 0.85))]);

        $intent = $this->parser()->parse('nak nasi banjir kuah campur', null);

        $this->assertInstanceOf(CravingIntent::class, $intent);
        $this->assertSame('nasi_kandar', $intent->concept);
        $this->assertSame('ai', $intent->source);
        $this->assertEqualsWithDelta(0.85, $intent->confidence, 1e-6);
        $this->assertSame('craving', AiJudgment::sole()->purpose);
    }

    public function test_none_or_low_confidence_falls_back_to_local_hint_or_null(): void
    {
        Http::fake(['openrouter.ai/*' => $this->judgmentResponse($this->cravingAnswer('none', 0.9))]);
        $this->assertNull($this->parser()->parse('hello there', null));

        Http::fake(['openrouter.ai/*' => $this->judgmentResponse($this->cravingAnswer('pizza', 0.4))]);
        $local = ['concept' => 'burger', 'kind' => FoodConceptKind::Category, 'confidence' => 0.7, 'searchTerms' => ['burger'], 'placeTypes' => ['hamburger_restaurant']];
        $intent = $this->parser()->parse('something bready', $local);
        $this->assertSame('burger', $intent->concept);
        $this->assertSame('taxonomy', $intent->source);
    }

    public function test_calibration_reports_brier_and_bands_for_labelled_rows(): void
    {
        $row = fn (float $cert, float $spam, string $decision, ?string $resolved) => AiJudgment::create([
            'run_id' => 'judg_'.uniqid(), 'purpose' => 'halal_triage', 'definition_version' => 1,
            'provider' => 'openrouter', 'model' => 'm', 'structured_mode' => 'json_schema', 'temperature' => 1, 'samples' => 3,
            'state_hash' => 'h', 'questions' => [], 'status' => 'ok',
            'answers' => [
                'mentions_certificate' => ['type' => 'binary', 'probability' => $cert],
                'mentions_pork_alcohol' => ['type' => 'binary', 'probability' => 0.1],
                'is_spam_or_irrelevant' => ['type' => 'binary', 'probability' => $spam],
                'supports_claim' => ['type' => 'score', 'score' => 2.4, 'maxLevel' => 3],
            ],
            'outcome' => ['decision' => $decision, 'claim' => 'certified', 'resolved' => $resolved],
        ]);
        $row(0.9, 0.05, 'approved', 'certified');
        $row(0.95, 0.1, 'approved', 'certified');
        $row(0.2, 0.9, 'rejected', null);
        // Unlabelled rows are ignored.
        AiJudgment::create(['run_id' => 'judg_x', 'purpose' => 'halal_triage', 'definition_version' => 1, 'provider' => 'openrouter', 'model' => 'm', 'structured_mode' => 'json_schema', 'temperature' => 1, 'samples' => 1, 'state_hash' => 'h', 'questions' => [], 'status' => 'ok', 'answers' => []]);

        // spam Brier: (0.05-0)^2 + (0.1-0)^2 + (0.9-1)^2 = 0.0025+0.01+0.01 = 0.0225 / 3 = 0.0075
        $this->artisan('judgments:calibration halal_triage')
            ->expectsOutputToContain('halal_triage v1: 3 labelled judgments')
            ->expectsOutputToContain('is_spam_or_irrelevant  n=3  Brier=0.0075')
            ->expectsOutputToContain('mentions_certificate  n=2')
            ->assertSuccessful();
    }

    public function test_prune_clears_old_state_but_keeps_rows(): void
    {
        $old = AiJudgment::create(['run_id' => 'judg_old', 'purpose' => 'craving', 'definition_version' => 1, 'provider' => 'openrouter', 'model' => 'm', 'structured_mode' => 'json_schema', 'temperature' => 0, 'samples' => 1, 'state' => ['evidence' => ['craving' => 'x']], 'state_hash' => 'h', 'questions' => [], 'status' => 'ok']);
        $old->forceFill(['created_at' => now()->subDays(120)])->save();
        $new = AiJudgment::create(['run_id' => 'judg_new', 'purpose' => 'craving', 'definition_version' => 1, 'provider' => 'openrouter', 'model' => 'm', 'structured_mode' => 'json_schema', 'temperature' => 0, 'samples' => 1, 'state' => ['evidence' => ['craving' => 'y']], 'state_hash' => 'h', 'questions' => [], 'status' => 'ok']);

        $this->artisan('judgments:prune')->assertSuccessful();

        $this->assertNull($old->fresh()->state);
        $this->assertSame('h', $old->fresh()->state_hash);
        $this->assertNotNull($new->fresh()->state);
    }

    public function test_system_health_widget_renders(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superadmin', 'status' => 'active']), 'web');
        Livewire::test(SystemHealthWidget::class)->assertOk()->assertSee('AI judgments today');
    }
}
