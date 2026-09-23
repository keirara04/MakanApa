<?php

namespace Tests\Feature\Judgment;

use App\Jobs\TriageHalalReport;
use App\Models\AiJudgment;
use App\Models\RestaurantSubmission;
use App\Services\RestaurantSubmissionModerationService;
use App\Support\Halal\HalalStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsHalalFixtures;
use Tests\Support\FakesJudgments;
use Tests\TestCase;

class HalalTriageTest extends TestCase
{
    use BuildsHalalFixtures, FakesJudgments, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->enableJudgments();
    }

    private function triageAnswers(float $cert, float $pork, float $spam, array $support): array
    {
        return [
            'mentions_certificate' => ['p_yes' => $cert],
            'mentions_pork_alcohol' => ['p_yes' => $pork],
            'supports_claim' => ['probabilities' => array_combine(['0', '1', '2', '3'], $support)],
            'is_spam_or_irrelevant' => ['p_yes' => $spam],
            'claim_vs_listing' => ['probabilities' => ['consistent' => 0.8, 'contradicts' => 0.05, 'unclear' => 0.15]],
        ];
    }

    private function submitVouch(string $claim, string $comment): int
    {
        $restaurant = $this->makeRestaurant();
        Sanctum::actingAs($this->makeUser(['created_at' => now()->subYear()]), ['*']);
        $id = $this->postJson("/api/v1/restaurants/{$restaurant->id}/halal-reports", ['claim' => $claim, 'comment' => $comment])->json('submission.id');
        $this->postJson("/api/v1/community/submissions/{$id}/submit")->assertOk();

        return $id;
    }

    public function test_submit_runs_triage_and_adds_bounded_ai_priority_without_touching_status(): void
    {
        Http::fake(['openrouter.ai/*' => $this->judgmentResponse($this->triageAnswers(0.95, 0.02, 0.03, [0, 0.05, 0.25, 0.7]))]);

        $id = $this->submitVouch('muslim_friendly', 'Owner is Muslim, JAKIM cert on wall beside cashier');
        $s = RestaurantSubmission::find($id);

        $this->assertSame('halal_triage', $s->triage['purpose']);
        $this->assertSame(1, $s->triage['version']);
        $this->assertEqualsWithDelta(0.95, $s->triage['answers']['mentions_certificate']['probability'], 1e-6);
        $this->assertSame(['ai_strong_evidence' => 10], $s->review_priority_breakdown['ai']);
        $this->assertSame(60, $s->review_priority); // base 50 + ai 10
        $this->assertSame(HalalStatus::Unknown, $s->restaurant->fresh()->halal_status);

        // 3 requests: sample 1 asks everything, samples 2-3 only the two Binary questions asked 3x.
        Http::assertSentCount(3);
        $this->assertSame('ok', AiJudgment::sole()->status);
    }

    public function test_spam_vouch_moves_down_the_queue(): void
    {
        Http::fake(['openrouter.ai/*' => $this->judgmentResponse($this->triageAnswers(0.05, 0.05, 0.92, [0.9, 0.1, 0, 0]))]);

        $s = RestaurantSubmission::find($this->submitVouch('muslim_friendly', 'asdf follow my ig'));

        $this->assertSame(['ai_likely_spam' => -15], $s->review_priority_breakdown['ai']);
        $this->assertSame(35, $s->review_priority);
    }

    public function test_retrying_the_job_does_not_duplicate_or_recall(): void
    {
        Http::fake(['openrouter.ai/*' => $this->judgmentResponse($this->triageAnswers(0.95, 0.02, 0.03, [0, 0.05, 0.25, 0.7]))]);
        $id = $this->submitVouch('muslim_friendly', 'Cert on wall');

        TriageHalalReport::dispatchSync($id);
        TriageHalalReport::dispatchSync($id);

        Http::assertSentCount(3);
        $this->assertSame(1, AiJudgment::count());
    }

    public function test_ai_failure_leaves_submission_working_with_rule_priority_only(): void
    {
        Http::fake(['openrouter.ai/*' => Http::response('down', 503)]);

        $s = RestaurantSubmission::find($this->submitVouch('muslim_friendly', 'Owner is Muslim'));

        $this->assertSame('pending', $s->status);
        $this->assertNull($s->triage);
        $this->assertSame([], $s->review_priority_breakdown['ai']);
        $this->assertSame(50, $s->review_priority);
        $this->assertSame('provider_error', AiJudgment::sole()->failure_reason);
    }

    public function test_admin_decision_labels_the_judgment_outcome(): void
    {
        Http::fake(['openrouter.ai/*' => $this->judgmentResponse($this->triageAnswers(0.95, 0.02, 0.03, [0, 0.05, 0.25, 0.7]))]);
        $id = $this->submitVouch('muslim_friendly', 'Cert on wall');

        app(RestaurantSubmissionModerationService::class)->approve(RestaurantSubmission::find($id), $this->makeAdmin());

        $judgment = AiJudgment::sole();
        $this->assertSame(['decision' => 'approved', 'claim' => 'muslim_friendly', 'resolved' => 'muslim_friendly'], $judgment->outcome);
        $this->assertNotNull($judgment->outcome_at);
    }

    public function test_disabled_purpose_skips_the_model(): void
    {
        config(['judgment.purposes.halal_triage.enabled' => false]);
        Http::fake();

        $s = RestaurantSubmission::find($this->submitVouch('muslim_friendly', 'Owner is Muslim'));

        Http::assertNothingSent();
        $this->assertNull($s->triage);
        $this->assertSame('disabled', AiJudgment::sole()->failure_reason);
    }
}
