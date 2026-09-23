<?php

namespace App\Services\Brain;

use App\Models\Decision;
use App\Models\DecisionRecommendation;
use App\Models\Restaurant;
use App\Models\TasteEvent;
use App\Models\User;
use App\Services\Community\CommunityPickStats;
use App\Services\RecommendationService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * The v2 decision pipeline shared by Decide (solo) and Nearby (pick one lah):
 *
 *   rank every eligible candidate (breakdowns kept) → diversity pass → adaptive exploration →
 *   persist the pool with breakdown/score_rank/selection_probability/reason facts → fingerprint.
 *
 * Reroll, Tune and What-if then work purely off what's persisted here.
 */
class MakanBrain
{
    /** Components every stored candidate carries even if unweighted, so Tune can reweight them. */
    public const TUNABLE = ['distance', 'rating', 'cheapEatsFit', 'popularityBonus', 'openCertainty', 'novelty'];

    public function __construct(
        private readonly RecommendationService $recommendations,
        private readonly CommunityPickStats $pickStats,
    ) {}

    /**
     * @param  array<int, array>  $restaurants
     * @param  array<string, mixed>  $preference  must contain 'brain' => DecisionBrainState
     * @param  array<string, mixed>  $decisionAttributes  the v1 Decision::create() attributes
     * @return array{decision: Decision, winner: ?array, winnerRow: ?DecisionRecommendation, craving: ?array, funnel: array}
     */
    public function decide(array $restaurants, array $preference, array $decisionAttributes, ?User $user, ?callable $randomSource = null): array
    {
        /** @var DecisionBrainState $brain */
        $brain = $preference['brain'];
        $craving = $preference['cravingIntent'] ?? null;

        $ranked = $this->recommendations->rankAll($restaurants, $preference);
        $funnel = $this->recommendations->lastFunnel();
        if ($brain->intentType !== 'anything') {
            $funnel['strong_match'] = count(array_filter($ranked, fn ($c) => $c['relevanceTier'] === 2));
        }

        $diverse = DiversityFilter::apply(
            $ranked,
            (int) Config::get('brain.pool_size', 8),
            $craving !== null && $craving->isRecognized(),
            $preference['knownChains'] ?? [],
        );
        $pool = $diverse['pool'];
        $funnel['diversified'] = $diverse['displaced'];

        $need = ExplorationPolicy::need($brain, $pool);
        $pick = ExplorationPolicy::pick($pool, $need, $brain->fatigueMode, $randomSource);
        $winner = $pick['index'] !== null ? $pool[$pick['index']] : null;

        return DB::transaction(function () use ($brain, $preference, $decisionAttributes, $pool, $pick, $winner, $need, $funnel, $user, $craving) {
            $decision = Decision::create([
                ...$decisionAttributes,
                'selected_restaurant_id' => $winner['restaurant']['id'] ?? null,
                'session_id' => $brain->sessionId,
                'algorithm_version' => Config::get('brain.algorithm_version', 'v2'),
                'taste_profile_version' => $brain->taste?->version,
                'selera_stage' => $brain->seleraStage,
                'intent_type' => $brain->intentType,
                'lens' => $brain->lens?->value,
                'tunes' => [],
                'context_snapshot' => $brain->context->toArray(),
                'weight_snapshot' => $winner['breakdown']['activeWeights'] ?? null,
                'candidate_count' => count($pool),
                'funnel' => $funnel,
                'exploration_level' => $need,
                'fatigue_mode' => $brain->fatigueMode,
                'reason_catalog_version' => (int) Config::get('brain.reason_catalog_version', 1),
            ]);

            $trace = $this->tracePool($pool, $pick['probabilities'], $preference);
            $extras = [
                'cravingRaw' => $craving?->raw !== null && trim($craving->raw) !== '' ? trim($craving->raw) : null,
                'moods' => $preference['moodTags'] ?? [],
                'communityCounts' => $this->pickStats->pickerCounts($user, array_column($trace, 'id'), $preference['latitude'] ?? null, $preference['longitude'] ?? null),
                'communityLabel' => CommunityPickStats::label($user),
            ];

            $winnerRow = null;
            foreach ($trace as $index => $entry) {
                $isWinner = $index === $pick['index'];
                $row = DecisionRecommendation::create([
                    'decision_id' => $decision->id,
                    'restaurant_id' => $entry['id'],
                    'rank' => $index + 1,
                    'score' => $entry['score'],
                    'score_rank' => $index + 1,
                    'selection_probability' => round($entry['probability'], 4),
                    'selected' => $isWinner,
                    'shown_at' => $isWinner ? now() : null,
                    'breakdown' => [
                        'components' => $entry['components'],
                        'weights' => $entry['weights'],
                        'tier' => $entry['tier'],
                        'facts' => $entry['facts'],
                    ],
                    // Facts for every candidate, not just the winner — reroll/tune read them for free.
                    'reason_facts' => ReasonComposer::compose($trace, $index, $brain, $extras),
                ]);
                $winnerRow = $isWinner ? $row : $winnerRow;
            }

            if ($winner) {
                Restaurant::whereKey($winner['restaurant']['id'])->increment('impressions_count');
            }

            return [
                'decision' => $decision,
                'winner' => $winner,
                'winnerRow' => $winnerRow,
                'craving' => $this->recommendations->cravingMatchStatus($pool, $craving),
                'funnel' => $funnel,
            ];
        });
    }

    /**
     * Framework-free snapshot of the pool for ReasonComposer / Counterfactual.
     *
     * @return array<int, array>
     */
    private function tracePool(array $pool, array $probabilities, array $preference): array
    {
        return array_map(function (array $candidate, int $index) use ($probabilities, $preference) {
            $restaurant = $candidate['restaurant'];
            $components = $candidate['breakdown']['components'];
            foreach (self::TUNABLE as $key) {
                $components[$key] ??= RecommendationService::componentValue($key, $restaurant, $preference, $candidate['distanceKm']);
            }

            return [
                'id' => $restaurant['id'],
                'name' => $restaurant['name'],
                'tier' => $candidate['relevanceTier'],
                'score' => round($candidate['score'], 4),
                'scoreRank' => $index + 1,
                'probability' => $probabilities[$index] ?? 0.0,
                'components' => array_map(fn ($v) => round((float) $v, 4), array_filter($components, fn ($v) => $v !== null)),
                'weights' => array_map(fn ($w) => round((float) $w, 4), $candidate['breakdown']['activeWeights']),
                'facts' => [
                    'distanceKm' => round($candidate['distanceKm'], 3),
                    'priceLevel' => $restaurant['price_level'] ?? null,
                    'rating' => $restaurant['rating'] ?? null,
                    'userRatingCount' => $restaurant['user_rating_count'] ?? null,
                    'category' => $restaurant['food_category'] ?? null,
                    'cuisines' => $restaurant['cuisines'] ?? [],
                    'openStatus' => $restaurant['open_status'] ?? 'unknown',
                    'halalStatus' => $restaurant['halal_status'] ?? null,
                    'name' => $restaurant['name'],
                ],
            ];
        }, $pool, array_keys($pool));
    }

    /** Rerolls + tunes already made in this decision's session — drives decision-fatigue mode. */
    public function sessionActions(Decision $decision): int
    {
        if ($decision->session_id === null) {
            return 0;
        }

        return (int) TasteEvent::query()
            ->where('session_id', $decision->session_id)
            ->whereIn('signal', ['reroll', 'tune'])
            ->distinct()
            ->count('decision_recommendation_id');
    }
}
