<?php

namespace Tests\Feature\Judgment;

use App\Models\AiJudgment;
use App\Models\Restaurant;
use App\Services\Judgment\JudgmentDefinition;
use App\Services\Judgment\JudgmentEngine;
use App\Services\Judgment\OpenRouterJudgmentEngine;
use App\Services\Judgment\Questions\BinaryQuestion;
use App\Services\Judgment\Questions\ChoiceQuestion;
use App\Services\Judgment\Questions\ScoreQuestion;
use App\Services\Judgment\Statistics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakesJudgments;
use Tests\TestCase;

class OpenRouterJudgmentEngineTest extends TestCase
{
    use FakesJudgments, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableJudgments();
        config(['judgment.purposes.test_purpose' => ['enabled' => true, 'daily_limit' => 100]]);
    }

    private function engine(): JudgmentEngine
    {
        return $this->app->make(JudgmentEngine::class);
    }

    private function definition(int $binarySamples = 1, string $comment = 'Saw JAKIM cert at the counter'): JudgmentDefinition
    {
        return new class($binarySamples, $comment) extends JudgmentDefinition
        {
            public function __construct(private int $samples, private string $comment) {}

            public function purpose(): string
            {
                return 'test_purpose';
            }

            public function version(): int
            {
                return 3;
            }

            public function questions(): array
            {
                return [
                    new BinaryQuestion('cert', 'Does `evidence.comment` describe a halal certificate?', samples: $this->samples),
                    new ChoiceQuestion('fit', 'Does the claim fit the listing?', ['consistent' => null, 'contradicts' => null, 'unclear' => null]),
                    new ScoreQuestion('strength', 'How strong is the evidence?', ['None', 'Weak', 'Moderate', 'Strong']),
                ];
            }

            public function buildState(array $context): array
            {
                return ['evidence' => ['comment' => $this->comment], 'context' => ['status' => 'unknown']];
            }
        };
    }

    private function answers(float $pYes = 0.9): array
    {
        return [
            'cert' => ['p_yes' => $pYes],
            'fit' => ['probabilities' => ['consistent' => 7, 'contradicts' => 1, 'unclear' => 2]],
            'strength' => ['probabilities' => ['0' => 0.0, '1' => 0.1, '2' => 0.3, '3' => 0.6]],
        ];
    }

    public function test_happy_path_returns_typed_answers_and_logs_one_row(): void
    {
        Http::fake(['openrouter.ai/*' => $this->judgmentResponse($this->answers())]);
        $restaurant = Restaurant::create(['name' => 'X', 'latitude' => 3, 'longitude' => 101, 'provider' => 'google', 'provider_place_id' => 'p1']);

        $result = $this->engine()->ask($this->definition(), [], $restaurant);

        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(0.9, $result->binary('cert')->probability, 1e-9);
        // Choice normalized from raw weights 7/1/2 in code.
        $this->assertSame('consistent', $result->choice('fit')->choice);
        $this->assertEqualsWithDelta(0.7, $result->choice('fit')->probabilityOf('consistent'), 1e-9);
        // Score expected value 0*0 + 1*.1 + 2*.3 + 3*.6 = 2.5
        $this->assertEqualsWithDelta(2.5, $result->score('strength')->score, 1e-9);

        $row = AiJudgment::sole();
        $this->assertSame('ok', $row->status);
        $this->assertSame($result->runId, $row->run_id);
        $this->assertStringStartsWith('judg_', $row->run_id);
        $this->assertSame(3, $row->definition_version);
        $this->assertSame('anthropic/claude-haiku-4.5', $row->model);
        $this->assertSame('test_purpose:'.$restaurant->getMorphClass().':'.$restaurant->id.':v3', $row->idempotency_key);
        $this->assertSame(120, $row->input_tokens);
        $this->assertCount(1, $row->attempts);

        Http::assertSent(function (Request $request) {
            $body = $request->data();
            $user = $body['messages'][1]['content'];

            return $body['response_format']['type'] === 'json_schema'
                && $body['response_format']['json_schema']['strict'] === true
                && $body['temperature'] == 0.0
                && str_contains($user, 'EVIDENCE TO JUDGE')
                && str_contains($user, 'Do not assume this is correct')
                && $body['response_format']['json_schema']['schema']['properties']['strength']['properties']['probabilities']['required'] === ['0', '1', '2', '3'];
        });
    }

    public function test_repeat_sampling_aggregates_mean_and_spread_and_only_resends_owed_questions(): void
    {
        Http::fakeSequence('openrouter.ai/*')
            ->push($this->judgmentResponse($this->answers(0.48))->wait()->getBody()->getContents())
            ->push(json_encode(['choices' => [['message' => ['content' => json_encode(['cert' => ['p_yes' => 0.99]])]]]]))
            ->push(json_encode(['choices' => [['message' => ['content' => json_encode(['cert' => ['p_yes' => 0.99]])]]]]));

        $result = $this->engine()->ask($this->definition(binarySamples: 3), []);

        $cert = $result->binary('cert');
        $this->assertSame(3, $cert->sampleCount());
        $this->assertEqualsWithDelta(0.82, $cert->probability, 1e-9);
        $this->assertEqualsWithDelta(Statistics::stdDev([0.48, 0.99, 0.99]), $cert->sampleStdDev, 1e-9);
        $this->assertCount(1, $result->choice('fit')->sampleChoices); // asked once only
        $this->assertCount(3, AiJudgment::sole()->attempts);
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $r) => $r->data()['temperature'] == 1.0);
    }

    public function test_idempotent_rerun_reuses_existing_result_without_calling_provider(): void
    {
        Http::fake(['openrouter.ai/*' => $this->judgmentResponse($this->answers())]);
        $restaurant = Restaurant::create(['name' => 'X', 'latitude' => 3, 'longitude' => 101, 'provider' => 'google', 'provider_place_id' => 'p1']);

        $first = $this->engine()->ask($this->definition(), [], $restaurant);
        $second = $this->engine()->ask($this->definition(), [], $restaurant);

        Http::assertSentCount(1);
        $this->assertTrue($second->reused);
        $this->assertSame($first->runId, $second->runId);
        $this->assertSame(1, AiJudgment::count());
    }

    public function test_failures_are_classified_and_logged_never_thrown(): void
    {
        $cases = [
            'rate_limited' => fn () => Http::response(['error' => 'slow down'], 429),
            'provider_error' => fn () => Http::response(['error' => 'boom'], 500),
            'timeout' => fn () => throw new ConnectionException('timed out'),
            'invalid_json' => fn () => Http::response(['choices' => [['message' => ['content' => 'I think yes']]]]),
            'schema_validation' => fn () => Http::response(['choices' => [['message' => ['content' => json_encode(['cert' => ['p_yes' => 'high']])]]]]),
        ];

        $current = null;
        Http::fake(['openrouter.ai/*' => function () use (&$current) {
            return $current();
        }]);

        foreach ($cases as $reason => $response) {
            $current = $response;

            $this->assertNull($this->engine()->ask($this->definition(), []), $reason);
            $row = AiJudgment::latest('id')->first();
            $this->assertSame('failed', $row->status, $reason);
            $this->assertSame($reason, $row->failure_reason);
            // Retryable failures get exactly one retry.
            $this->assertCount(in_array($reason, ['rate_limited', 'provider_error', 'timeout'], true) ? 2 : 1, $row->attempts, $reason);
        }
    }

    public function test_disabled_purpose_and_exhausted_budget_make_no_http_call(): void
    {
        Http::fake();

        config(['judgment.purposes.test_purpose.enabled' => false]);
        $this->assertNull($this->engine()->ask($this->definition(), []));
        $this->assertSame('disabled', AiJudgment::latest('id')->first()->failure_reason);

        config(['judgment.purposes.test_purpose' => ['enabled' => true, 'daily_limit' => 0]]);
        $this->assertNull($this->engine()->ask($this->definition(), []));
        $row = AiJudgment::latest('id')->first();
        $this->assertSame('budget_exhausted', $row->status);

        Http::assertNothingSent();
    }

    public function test_oversized_state_fails_without_calling_provider(): void
    {
        Http::fake();
        config(['judgment.limits.max_state_chars' => 50]);

        $this->assertNull($this->engine()->ask($this->definition(comment: str_repeat('long evidence ', 20)), []));

        $this->assertSame('state_too_large', AiJudgment::sole()->failure_reason);
        Http::assertNothingSent();
    }

    public function test_tools_mode_sends_forced_tool_call_and_parses_arguments(): void
    {
        config(['judgment.mode' => 'tools']);
        Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['tool_calls' => [
            ['function' => ['name' => 'submit_judgment', 'arguments' => json_encode($this->answers(0.3))]],
        ]]]]])]);

        $result = $this->engine()->ask($this->definition(), []);

        $this->assertEqualsWithDelta(0.3, $result->binary('cert')->probability, 1e-9);
        Http::assertSent(fn (Request $r) => ($r->data()['tool_choice']['function']['name'] ?? null) === 'submit_judgment'
            && ! isset($r->data()['response_format']));
    }

    public function test_no_key_uses_null_engine(): void
    {
        config(['services.openrouter.api_key' => '']);
        $this->app->forgetInstance(JudgmentEngine::class);
        Http::fake();

        $this->assertNull($this->app->make(JudgmentEngine::class)->ask($this->definition(), []));
        $this->assertNotInstanceOf(OpenRouterJudgmentEngine::class, $this->app->make(JudgmentEngine::class));
        Http::assertNothingSent();
        $this->assertSame(0, AiJudgment::count());
    }

    public function test_confidence_is_derived_from_distribution(): void
    {
        $this->assertEqualsWithDelta(1.0, Statistics::concentration(['a' => 1.0, 'b' => 0.0, 'c' => 0.0]), 1e-9);
        $this->assertEqualsWithDelta(0.0, Statistics::concentration(['a' => 1 / 3, 'b' => 1 / 3, 'c' => 1 / 3]), 1e-9);
        $this->assertEqualsWithDelta(0.3, Statistics::margin(['a' => 0.6, 'b' => 0.3, 'c' => 0.1]), 1e-9);
    }
}
