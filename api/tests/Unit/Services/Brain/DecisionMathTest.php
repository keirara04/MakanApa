<?php

namespace Tests\Unit\Services\Brain;

use App\Services\Brain\Counterfactual;
use App\Services\Brain\DiversityFilter;
use App\Services\Brain\ExplorationPolicy;
use App\Services\Brain\ReasonCatalog;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class DecisionMathTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('brain.features.diversity', true);
    }

    private function candidate(int $id, float $score, int $tier = 2, ?string $category = 'nasi', string $name = ''): array
    {
        return [
            'restaurant' => ['id' => $id, 'name' => $name ?: "Place {$id}", 'food_category' => $category, 'rating' => 4.0, 'open_status' => 'open', 'user_rating_count' => 100],
            'score' => $score, 'relevanceTier' => $tier, 'distanceKm' => 1.0,
        ];
    }

    public function test_strong_craving_leaves_almost_no_room_to_explore(): void
    {
        $this->assertLessThan(0.05, ExplorationPolicy::baseNeed(0.9, 'strong', false, 0, 0));
        $this->assertGreaterThan(0.3, ExplorationPolicy::baseNeed(0.0, 'starting', true, 0, 2));
    }

    public function test_sampling_never_leaves_the_top_relevance_tier(): void
    {
        $pool = [$this->candidate(1, 50, 2), $this->candidate(2, 49, 2), $this->candidate(3, 99, 1)];

        foreach ([0.0, 0.5, 0.999] as $roll) {
            $pick = ExplorationPolicy::pick($pool, 1.0, false, fn () => $roll);
            $this->assertContains($pick['index'], [0, 1]);
            $this->assertSame(0.0, $pick['probabilities'][2]);
        }
    }

    public function test_fatigue_picks_the_safest_near_top_option(): void
    {
        $risky = $this->candidate(1, 80);
        $risky['restaurant']['open_status'] = 'unknown';
        $risky['restaurant']['rating'] = 3.9;
        $safe = $this->candidate(2, 77);
        $safe['restaurant']['rating'] = 4.7;

        $pick = ExplorationPolicy::pick([$risky, $safe], 0.9, true);

        $this->assertSame(1, $pick['index']);
        $this->assertSame(1.0, $pick['probabilities'][1]);
    }

    public function test_diversity_caps_category_and_brand_unless_explicit_craving(): void
    {
        $ranked = [
            $this->candidate(1, 90, 2, 'nasi', 'Nasi Kandar Pelita'), $this->candidate(2, 89, 2, 'nasi', 'Nasi Kandar Pelita Ampang'),
            $this->candidate(3, 88, 2, 'nasi', 'Nasi Kandar Line Clear'), $this->candidate(4, 70, 2, 'burger'),
            $this->candidate(5, 60, 2, 'cafe'),
        ];

        $diverse = DiversityFilter::apply($ranked, 4, false, []);
        $this->assertSame([1, 2, 4, 5], array_map(fn ($c) => $c['restaurant']['id'], $diverse['pool']));
        $this->assertSame(1, $diverse['displaced']);

        $craving = DiversityFilter::apply($ranked, 4, true, []);
        $this->assertSame([1, 2, 3, 4], array_map(fn ($c) => $c['restaurant']['id'], $craving['pool']));
    }

    public function test_causal_deciding_factor_can_differ_from_the_largest_lift(): void
    {
        // A beats B hugely on community (the largest lift), but C ties A there — so community
        // isn't what separates A from its real rival. Rating is: without it, C wins.
        $w = ['rating' => 10, 'distance' => 10, 'community' => 10];
        $pool = [
            ['id' => 1, 'name' => 'A', 'tier' => 2, 'components' => ['rating' => 1.0, 'distance' => 0.6, 'community' => 1.0], 'weights' => $w],
            ['id' => 2, 'name' => 'B', 'tier' => 2, 'components' => ['rating' => 0.5, 'distance' => 0.55, 'community' => 0.0], 'weights' => $w],
            ['id' => 3, 'name' => 'C', 'tier' => 2, 'components' => ['rating' => 0.9, 'distance' => 0.65, 'community' => 1.0], 'weights' => $w],
        ];

        $lifts = Counterfactual::lifts($pool, 0);
        arsort($lifts);
        $this->assertSame('community', array_key_first($lifts));

        $flips = Counterfactual::flips($pool, 0, Counterfactual::lifts($pool, 0));
        $this->assertSame('rating', $flips[0]['component']);
        $this->assertSame(2, $flips[0]['winnerIndex']);
        $this->assertCount(1, $flips);
    }

    public function test_catalog_render_is_deterministic_per_seed_and_one_line_per_family(): void
    {
        $facts = ['catalogVersion' => 1, 'fit' => 'strong', 'decidingFactor' => ['component' => 'distance', 'causal' => true], 'reasons' => [
            ['family' => 'match', 'key' => 'craving_match', 'lift' => 1, 'facts' => ['craving' => 'ayam penyet']],
            ['family' => 'edge', 'key' => 'close', 'lift' => 8.4, 'facts' => ['distanceM' => 400, 'rank' => 1, 'poolSize' => 5]],
            ['family' => 'moment', 'key' => 'ctx_rain', 'lift' => 1, 'facts' => []],
        ]];

        $a = ReasonCatalog::render($facts, 42);
        $b = ReasonCatalog::render($facts, 42);

        $this->assertSame($a, $b);
        $this->assertCount(3, $a['reasons']);
        $this->assertSame(['match', 'edge', 'moment'], array_column($a['reasons'], 'family'));
        $this->assertStringContainsString('ayam penyet', $a['reasons'][0]['text']);
        $this->assertStringContainsString('400 m', $a['reasons'][1]['text']);
        $this->assertSame('Deciding factor: closest strong match.', $a['decidingFactor']);
    }

    public function test_thinking_trace_reads_the_real_funnel(): void
    {
        $trace = ReasonCatalog::thinkingTrace(['checked' => 31, 'closed' => 7, 'non_halal' => 3, 'over_budget' => 9, 'eligible' => 12, 'strong_match' => 5], 'craving', true);

        $this->assertSame(['Checked 31 nearby spots', 'Removed 7 closed', 'Removed 3 non-halal', '12 within budget', '5 strongly matched your craving', 'Picked this one'], $trace);
    }
}
