<?php

namespace App\Services\Brain;

use Illuminate\Support\Facades\Config;

/**
 * Builds the *facts* behind "Kenapa ni?" — never sentences (ReasonCatalog renders those at
 * response time, so copy can change without a migration). At most one reason per family:
 *
 *   MATCH  — why it fits you   (craving, mood, lens, supper-slot habit, pulse, Selera)
 *   EDGE   — why it beat the alternatives (comparative: closest / cheapest / best rated of N)
 *   MOMENT — why now            (rain, supper, Friday, iftar, streak-breaker, fatigue, wildcard)
 *
 * The deciding factor is causal where possible: the signal whose removal changes the winner.
 */
final class ReasonComposer
{
    /** Components that can be an EDGE reason, and the reason key each maps to. */
    private const EDGE_KEYS = [
        'distance' => 'close',
        'rating' => 'rating',
        'cheapEatsFit' => 'budget_fit',
        'community' => 'community_picks',
        'reviewVolumeBonus' => 'hidden_gem',
        'popularityBonus' => 'popular',
        'halalConfidence' => 'halal_verified',
    ];

    /**
     * @param  array<int, array>  $pool  trace pool entries (see DecisionTraceWriter::tracePool)
     * @param  array{cravingRaw: ?string, moods: string[], communityCounts: array<int,int>, communityLabel: ?string}  $extras
     */
    public static function compose(array $pool, int $index, DecisionBrainState $brain, array $extras): array
    {
        $winner = $pool[$index];
        $lifts = Counterfactual::lifts($pool, $index);
        // Only meaningful for the top-ranked candidate — a wildcard didn't win on any signal.
        $flips = ($winner['scoreRank'] ?? 1) === 1 ? Counterfactual::flips($pool, $index, $lifts) : [];
        $tierPool = array_values(array_filter($pool, fn ($c) => $c['tier'] === $winner['tier']));

        $reasons = array_values(array_filter([
            self::match($winner, $brain, $extras),
            self::edge($winner, $lifts, $pool, $tierPool, $extras),
            self::moment($winner, $brain),
        ]));

        return [
            'catalogVersion' => (int) Config::get('brain.reason_catalog_version', 1),
            'decidingFactor' => self::decidingFactor($pool, $index, $lifts, $flips),
            'whatIf' => Config::get('brain.features.what_if') ? count($flips) : 0,
            'reasons' => $reasons,
            'fit' => self::fit($pool, $index, $brain),
        ];
    }

    private static function match(array $winner, DecisionBrainState $brain, array $extras): ?array
    {
        $c = $winner['components'];
        $f = $winner['facts'];

        if (($c['relevance'] ?? 0) >= 0.8 && ! empty($extras['cravingRaw'])) {
            return self::reason('match', 'craving_match', $c['relevance'], ['craving' => $extras['cravingRaw']]);
        }
        if (($c['mood'] ?? 0) >= 0.67 && ! empty($extras['moods'])) {
            return self::reason('match', 'mood_match', $c['mood'], ['moods' => $extras['moods']]);
        }
        if ($brain->lens !== null) {
            return self::reason('match', 'lens_'.$brain->lens->value, 1.0, [
                'priceLevel' => $f['priceLevel'], 'distanceM' => (int) round($f['distanceKm'] * 1000), 'community' => $extras['communityLabel'],
            ]);
        }
        if ($f['category'] && SeleraScorer::slotAffinity($brain, $f['category']) > 0.3) {
            return self::reason('match', 'selera_slot', 1.0, ['slot' => $brain->context->mealSlot, 'category' => $f['category']]);
        }
        if ($f['category'] && $brain->pulse->delta('category', $f['category']) > 0.3) {
            return self::reason('match', 'pulse', 1.0, ['category' => $f['category']]);
        }
        if (($c['personalFit'] ?? 0) >= 0.7) {
            return self::reason('match', 'selera_match', $c['personalFit'], ['category' => $f['category'], 'cuisine' => $f['cuisines'][0] ?? null]);
        }

        return null;
    }

    private static function edge(array $winner, array $lifts, array $pool, array $tierPool, array $extras): ?array
    {
        $floor = (float) Config::get('brain.reasons.lift_floor', 1.0);
        arsort($lifts);
        $f = $winner['facts'];
        $n = count($tierPool);

        foreach ($lifts as $component => $lift) {
            $key = self::EDGE_KEYS[$component] ?? null;
            if ($key === null || $lift < $floor) {
                continue;
            }

            $facts = match ($key) {
                'close' => ['distanceM' => (int) round($f['distanceKm'] * 1000), 'rank' => self::rank($tierPool, $winner, fn ($c) => $c['facts']['distanceKm']), 'poolSize' => $n],
                'rating' => $f['rating'] === null ? null : ['rating' => $f['rating'], 'rank' => self::rank($tierPool, $winner, fn ($c) => -($c['facts']['rating'] ?? 0)), 'poolSize' => $n],
                'budget_fit' => $f['priceLevel'] === null ? null : ['priceLevel' => $f['priceLevel'], 'rank' => self::rank($tierPool, $winner, fn ($c) => $c['facts']['priceLevel'] ?? 9), 'poolSize' => $n],
                'community_picks' => ($extras['communityCounts'][$winner['id']] ?? 0) >= 2
                    ? ['pickers' => $extras['communityCounts'][$winner['id']], 'community' => $extras['communityLabel']] : null,
                'hidden_gem' => ['reviews' => $f['userRatingCount'], 'rating' => $f['rating']],
                'popular' => ['reviews' => $f['userRatingCount']],
                'halal_verified' => $f['halalStatus'] === 'certified' ? ['status' => $f['halalStatus']] : null,
            };
            if ($facts !== null) {
                return self::reason('edge', $key, $lift, $facts);
            }
        }

        // No component stood out, but real community evidence is still worth saying.
        if (($extras['communityCounts'][$winner['id']] ?? 0) >= 3) {
            return self::reason('edge', 'community_picks', 0.0, ['pickers' => $extras['communityCounts'][$winner['id']], 'community' => $extras['communityLabel']]);
        }

        return null;
    }

    private static function moment(array $winner, DecisionBrainState $brain): ?array
    {
        $c = $winner['components'];
        $f = $winner['facts'];
        $ctx = $brain->context;
        $open = $f['openStatus'] === 'open';

        if ($brain->fatigueMode) {
            return self::reason('moment', 'fatigue', 1.0, []);
        }
        if (($winner['probability'] ?? 1) < (float) Config::get('brain.exploration.wildcard_probability', 0.2) || ($winner['scoreRank'] ?? 1) >= 3) {
            return self::reason('moment', 'wildcard', 1.0, []);
        }

        $candidates = [
            ['ctx_rain', $ctx->isActive('rain') && ($c['distance'] ?? 0) >= 0.5, ['confidence' => $ctx->confidence('rain'), 'distanceM' => (int) round($f['distanceKm'] * 1000)]],
            ['ctx_sahur', $ctx->isActive('sahur') && $open, []],
            ['ctx_iftar', $ctx->isActive('iftar'), ['distanceM' => (int) round($f['distanceKm'] * 1000)]],
            ['ctx_friday', $ctx->isActive('friday') && $open, []],
            ['ctx_supper', $ctx->isActive('supper') && $open, []],
            ['ctx_month_end', $ctx->isActive('month_end') && ($c['cheapEatsFit'] ?? 0) >= 0.7, ['priceLevel' => $f['priceLevel']]],
        ];
        foreach ($candidates as [$key, $active, $facts]) {
            if ($active) {
                return self::reason('moment', $key, 1.0, $facts);
            }
        }

        $streak = SeleraScorer::streakCategory($brain);
        if ($streak !== null && $f['category'] !== $streak && ($c['novelty'] ?? 0) >= 1.0) {
            return self::reason('moment', 'novelty', 1.0, ['streak' => $streak]);
        }

        return null;
    }

    /** Causal where possible; the largest positive lift otherwise; "exploration" for a wildcard. */
    private static function decidingFactor(array $pool, int $index, array $lifts, array $flips): ?array
    {
        if (($pool[$index]['scoreRank'] ?? 1) !== 1) {
            return ['component' => 'exploration', 'causal' => false];
        }

        if ($flips !== []) {
            return [
                'component' => $flips[0]['component'],
                'causal' => true,
                'runnerUp' => $pool[$flips[0]['winnerIndex']]['name'],
                'withinTier' => $pool[$index]['tier'] === 2 && count(array_filter($pool, fn ($c) => $c['tier'] === 2)) > 1,
            ];
        }

        $positive = array_filter($lifts, fn ($l) => $l > 0);
        if ($positive === []) {
            return null;
        }
        arsort($positive);

        return ['component' => array_key_first($positive), 'causal' => false];
    }

    /** Not a probability — a plain-language label: strong / good / wildcard. */
    private static function fit(array $pool, int $index, DecisionBrainState $brain): string
    {
        $winner = $pool[$index];
        if (($winner['probability'] ?? 1) < (float) Config::get('brain.exploration.wildcard_probability', 0.2) || ($winner['scoreRank'] ?? 1) >= 3) {
            return 'wildcard';
        }

        $scores = array_column($pool, 'score');
        rsort($scores);
        $gap = ($scores[0] ?? 0) - ($scores[1] ?? 0);
        $mean = $scores ? array_sum($scores) / count($scores) : 0;
        $intentMatched = $brain->intentType === 'anything' || $winner['tier'] === 2;
        $tasteOk = ! $brain->tasteMature || ($winner['components']['personalFit'] ?? 0.5) >= 0.5;

        return ($winner['scoreRank'] === 1 && $gap >= 8 && $mean >= 45 && $intentMatched && $tasteOk) ? 'strong' : 'good';
    }

    private static function rank(array $tierPool, array $winner, callable $metric): int
    {
        $values = array_map($metric, $tierPool);
        sort($values);

        return array_search($metric($winner), $values, true) + 1;
    }

    private static function reason(string $family, string $key, float $lift, array $facts): array
    {
        return ['family' => $family, 'key' => $key, 'lift' => round($lift, 3), 'facts' => $facts];
    }
}
