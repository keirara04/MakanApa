<?php

namespace Tests\Feature\Judgment;

use App\Filament\Pages\AiCalibration;
use App\Filament\Resources\AiJudgments\AiJudgmentResource;
use App\Filament\Resources\AiJudgments\Pages\ListAiJudgments;
use App\Filament\Resources\AiJudgments\Pages\ViewAiJudgment;
use App\Filament\Widgets\AiJudgmentUsageChart;
use App\Models\AiJudgment;
use App\Models\RestaurantSubmission;
use App\Support\Halal\HalalStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\BuildsHalalFixtures;
use Tests\TestCase;

class AiJudgmentAdminTest extends TestCase
{
    use BuildsHalalFixtures, RefreshDatabase;

    private function judgment(array $overrides = []): AiJudgment
    {
        return AiJudgment::create([
            'run_id' => 'judg_'.uniqid(), 'purpose' => 'halal_triage', 'definition_version' => 1,
            'provider' => 'openrouter', 'model' => 'anthropic/claude-haiku-4.5', 'structured_mode' => 'json_schema',
            'temperature' => 1, 'samples' => 3, 'state' => ['evidence' => ['comment' => 'JAKIM cert by cashier']], 'state_hash' => str_repeat('a', 64),
            'questions' => [['id' => 'mentions_certificate', 'type' => 'yes_no']],
            'answers' => [
                'mentions_certificate' => ['type' => 'binary', 'probability' => 0.93, 'sampleCount' => 3, 'sampleValues' => [0.9, 0.95, 0.94], 'sampleStdDev' => 0.02],
                'supports_claim' => ['type' => 'score', 'score' => 2.5, 'maxLevel' => 3, 'probabilities' => ['0' => 0, '1' => 0.1, '2' => 0.3, '3' => 0.6], 'confidence' => 0.4, 'sampleCount' => 1],
                'claim_vs_listing' => ['type' => 'choice', 'choice' => 'consistent', 'probabilities' => ['consistent' => 0.8, 'contradicts' => 0.05, 'unclear' => 0.15], 'confidence' => 0.5, 'margin' => 0.65, 'voteShare' => 1, 'sampleChoices' => ['consistent']],
            ],
            'attempts' => [['sample' => 0, 'retry' => 0, 'httpStatus' => 200, 'latencyMs' => 820, 'failureReason' => null]],
            'input_tokens' => 400, 'output_tokens' => 60, 'latency_ms' => 900, 'status' => 'ok',
            ...$overrides,
        ]);
    }

    public function test_admin_can_list_filter_and_view_judgments(): void
    {
        $this->actingAs($this->makeAdmin(), 'web');
        $restaurant = $this->makeRestaurant();
        $report = $this->makeHalalReport($restaurant, $this->makeUser(), HalalStatus::Certified);
        $ok = $this->judgment(['subject_type' => (new RestaurantSubmission)->getMorphClass(), 'subject_id' => $report->id, 'outcome' => ['decision' => 'approved', 'claim' => 'certified', 'resolved' => 'certified']]);
        $failed = $this->judgment(['status' => 'failed', 'failure_reason' => 'rate_limited', 'answers' => null, 'error' => 'slow down']);

        Livewire::test(ListAiJudgments::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$ok, $failed])
            ->filterTable('status', 'failed')
            ->assertCanSeeTableRecords([$failed])
            ->assertCanNotSeeTableRecords([$ok]);

        Livewire::test(ViewAiJudgment::class, ['record' => $ok->id])
            ->assertOk()
            ->assertSee('mentions_certificate')
            ->assertSee('0.93')
            ->assertSee('JAKIM cert by cashier')
            ->assertSee('820 ms');

        Livewire::test(ViewAiJudgment::class, ['record' => $failed->id])->assertOk()->assertSee('slow down');
    }

    public function test_calibration_page_and_usage_chart_render(): void
    {
        $this->actingAs($this->makeAdmin(), 'web');
        $this->judgment(['outcome' => ['decision' => 'approved', 'claim' => 'certified', 'resolved' => 'certified']]);

        Livewire::test(AiCalibration::class)->assertOk()->assertSee('mentions_certificate')->assertSee('1 labelled judgments');
        Livewire::test(AiCalibration::class)->set('purpose', 'non_halal_second_opinion')->assertOk()->assertSee('No labelled judgments yet');
        Livewire::test(AiJudgmentUsageChart::class)->assertOk();
    }

    public function test_judgments_cannot_be_created_or_edited(): void
    {
        $this->assertFalse(AiJudgmentResource::canCreate());
        $this->assertArrayNotHasKey('edit', AiJudgmentResource::getPages());
    }
}
