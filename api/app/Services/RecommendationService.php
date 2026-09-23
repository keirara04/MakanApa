<?php

namespace App\Services;

use App\Services\Brain\DecisionBrainState;
use App\Services\Brain\SeleraScorer;
use App\Services\Craving\CravingIntent;
use App\Support\FoodTaxonomy;
use App\Support\Halal\HalalStatus;
use App\Support\ScoreWeights;
use App\Support\Vibe;

/**
 * MakanApa Recommendation v1.
 * Server-authoritative — there is no client-side scoring mirror to keep in sync (an earlier
 * iOS-side copy was removed; this is the only implementation).
 *
 * Restaurant arrays passed to this service use the shape:
 * ['id', 'name', 'signature_dish', 'food_category', 'latitude', 'longitude', 'price_level',
 *  'rating', 'is_active', 'cuisines' => string[], 'tags' => string[]]
 *
 * Preference arrays use the shape:
 * ['moodTags' => string[], 'cuisines' => string[], 'cravingIntent' => ?CravingIntent,
 *  'budgetMax' => int, 'maxDistanceKm' => float, 'latitude' => float, 'longitude' => float]
 */
class RecommendationService
{
    /** Rank weights for weighted-random pick among the top 5 candidates. */
    private const RANK_WEIGHTS = [0.40, 0.25, 0.17, 0.11, 0.07];

    /**
     * Counts from the most recent eligibleRestaurants() call — what the hard gates removed.
     * Feeds Makan Brain's thinking trace ("Removed 7 closed → …"); computed inline, no extra pass.
     *
     * @var array<string, int>
     */
    private array $lastFunnel = [];

    public static function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }

    private static function tagMatchComponent(array $selected, array $candidate): float
    {
        if (empty($selected)) {
            return 0;
        }
        $matched = count(array_intersect($selected, $candidate));

        return $matched / count($selected);
    }

    /**
     * Ranking signal, not an availability guarantee: this can only credit a restaurant whose
     * *live-synced* tags/food_category/name/signature_dish happen to reflect the resolved
     * concept — a restaurant that genuinely serves it but isn't tagged/named for it scores 0
     * here, same as one that doesn't serve it at all. Dual retrieval (PlacesService's Text
     * Search call) is what actually rescues those candidates into the pool in the first place;
     * this only ranks what's already there.
     */
    private static function relevanceComponent(CravingIntent $intent, array $restaurant): float
    {
        $concept = $intent->concept;
        if ($concept !== null) {
            if (in_array($concept, $restaurant['tags'] ?? [], true)) {
                return 1.0;
            }
            $foodCategory = FoodTaxonomy::foodCategoryFor($concept);
            if ($foodCategory !== null && ($restaurant['food_category'] ?? null) === $foodCategory) {
                return 1.0;
            }
        }

        // Falls back to the raw craving text when nothing resolved to a concept — this mirrors
        // CravingIntent::primarySearchTerm(), which is exactly what PlacesService used to find
        // Text Search candidates in the first place. Without this fallback, a restaurant the
        // dual-retrieval Text Search call successfully found for an unresolved craving (e.g.
        // "roti john") could never be credited here, silently discarding the whole point of
        // that retrieval call.
        $terms = ! empty($intent->searchTerms) ? $intent->searchTerms : [$intent->raw];

        $name = strtolower($restaurant['name'] ?? '');
        $signatureDish = strtolower($restaurant['signature_dish'] ?? '');
        foreach ($terms as $term) {
            $term = strtolower(trim($term));
            if ($term === '') {
                continue;
            }
            if (($name !== '' && str_contains($name, $term)) || ($signatureDish !== '' && str_contains($signatureDish, $term))) {
                return 0.8;
            }
        }

        return 0;
    }

    /** Reads the effective status Restaurant::toRecommendationArray() already resolved (expiry applied). */
    public static function isNonHalal(array $restaurant): bool
    {
        return ($restaurant['halal_status'] ?? HalalStatus::Unknown->value) === HalalStatus::NonHalal->value;
    }

    private static function halalConfidenceComponent(array $restaurant): float
    {
        return (HalalStatus::tryFrom($restaurant['halal_status'] ?? '') ?? HalalStatus::Unknown)->confidence();
    }

    private static function distanceComponent(float $distanceKm, float $maxDistanceKm): float
    {
        if ($maxDistanceKm <= 0) {
            return 0;
        }

        return max(0, 1 - $distanceKm / $maxDistanceKm);
    }

    private static function ratingComponent(?float $rating): float
    {
        if ($rating === null) {
            return 0.5;
        }

        return min(1, max(0, $rating / 5.0));
    }

    /**
     * Smooth bonus (never a hard cutoff) peaking for a "not brand new, not viral" review count —
     * exactly the band a genuinely low-key spot tends to sit in. Below 5 reviews there's barely
     * any evidence at all, so no bonus; above ~5000 it's clearly not a hidden spot anymore.
     */
    private static function reviewVolumeBonusComponent(?int $userRatingCount): float
    {
        if ($userRatingCount === null || $userRatingCount < 5) {
            return 0.0;
        }
        if ($userRatingCount < 20) {
            return ($userRatingCount - 5) / 15 * 0.7 + 0.3;
        }
        if ($userRatingCount <= 800) {
            return 1.0;
        }
        if ($userRatingCount <= 5000) {
            return max(0.0, 1 - ($userRatingCount - 800) / 4200);
        }

        return 0.0;
    }

    /** Inverse of reviewVolumeBonusComponent — rewards higher review counts, log-scaled so one viral outlier doesn't totally dominate. */
    private static function popularityBonusComponent(?int $userRatingCount): float
    {
        if ($userRatingCount === null || $userRatingCount <= 0) {
            return 0.0;
        }

        return min(1.0, log10($userRatingCount + 1) / log10(5000));
    }

    private static function cafeRelevanceComponent(array $restaurant): float
    {
        if (($restaurant['food_category'] ?? null) === 'cafe') {
            return 1.0;
        }

        return in_array('cafe', $restaurant['tags'] ?? [], true) ? 1.0 : 0.0;
    }

    private static function cheapEatsFitComponent(array $restaurant): float
    {
        $priceLevel = $restaurant['price_level'] ?? null;

        return match (true) {
            $priceLevel === null => 0.5,
            $priceLevel <= 1 => 1.0,
            $priceLevel === 2 => 0.4,
            default => 0.0,
        };
    }

    /**
     * Best-effort only — candidate scoring has just the open_now snapshot (open_status), not
     * actual closing times (those only get fetched for the winner, in enrichWinner()). Being
     * open right now is a weak proxy for "open late," not a real late-hours signal — flagged in
     * the plan as a known limitation, not something to over-promise precision on.
     */
    private static function lateNightFitComponent(array $restaurant): float
    {
        return ($restaurant['open_status'] ?? 'unknown') === 'open' ? 1.0 : 0.5;
    }

    private static function vibeRelevanceComponent(Vibe $vibe, array $restaurant): float
    {
        $isCafeLeaning = ($restaurant['food_category'] ?? null) === 'cafe'
            || in_array('cafe', $restaurant['tags'] ?? [], true);

        return match ($vibe) {
            Vibe::Dessert => ($restaurant['food_category'] ?? null) === 'dessert' ? 1.0 : 0.0,
            Vibe::Brunch => ($restaurant['food_category'] ?? null) === 'breakfast' ? 1.0 : 0.0,
            Vibe::Coffee, Vibe::Study, Vibe::Chill => $isCafeLeaning ? 1.0 : 0.0,
            // No reliable per-candidate signal for actual late hours at scoring time — same
            // caveat as lateNightFitComponent. Neutral rather than a fabricated 0/1 split.
            Vibe::LateNight => 0.5,
        };
    }

    /**
     * Bayesian-smoothed, not a raw accepted/impressions ratio — a 1-impression/1-accept
     * restaurant would otherwise look "better" than a 100-impression/70-accept one purely from
     * small-sample noise. A 0-impression restaurant scores exactly the neutral prior, so cold
     * start never drags a candidate down.
     *
     * @param  array{success_rate: float, weight: float}  $prior
     */
    private static function communityScoreComponent(array $restaurant, array $prior): float
    {
        $impressions = (int) ($restaurant['impressions_count'] ?? 0);
        $accepted = (int) ($restaurant['accepted_count'] ?? 0);
        $priorSuccesses = $prior['success_rate'] * $prior['weight'];

        return ($accepted + $priorSuccesses) / ($impressions + $prior['weight']);
    }

    /**
     * Rewards a candidate whose price_level/food_category matches what this installation has
     * actually accepted before — the mode/most-frequent value across its recent accepted
     * decisions. Only ever called once the caller has confirmed enough history exists (see
     * scoreBreakdown()'s doc comment) — this method itself doesn't gate on sample size.
     *
     * @param  array{price_levels: int[], food_categories: string[]}  $installationHistory
     */
    private static function personalFitComponent(array $restaurant, array $installationHistory): float
    {
        $priceMatch = in_array($restaurant['price_level'] ?? null, $installationHistory['price_levels'] ?? [], true) ? 1.0 : 0.0;
        $categoryMatch = in_array($restaurant['food_category'] ?? null, $installationHistory['food_categories'] ?? [], true) ? 1.0 : 0.0;

        return ($priceMatch + $categoryMatch) / 2;
    }

    /**
     * Case-insensitive substring match against a configured chain-name list — penalizes, never
     * excludes (a chain can still win if it's genuinely the best nearby option). Every overlay
     * that includes this ships its weight at 0 (see ScoreWeights::discoveryOverlay()) until real
     * usage shows chains actually dominating Low-key/Cafe results — the method exists so turning
     * it on later is a one-line config change, not new code.
     *
     * @param  string[]  $knownChains
     */
    private static function nonChainBonusComponent(string $name, array $knownChains): float
    {
        $lowerName = strtolower($name);
        foreach ($knownChains as $chain) {
            if ($chain !== '' && str_contains($lowerName, strtolower($chain))) {
                return 0.0;
            }
        }

        return 1.0;
    }

    /**
     * Buckets relevanceComponent()/tagMatchComponent()'s output into a coarse tier used to GATE
     * ranking (see topCandidates()) before mode/community signals get to reorder anything.
     * relevanceComponent() only ever produces {0, 0.8, 1.0} today, which already is a natural
     * 3-tier scale — this just names those tiers rather than inventing new thresholds.
     */
    private static function tierFromRelevance(float $relevance): int
    {
        return match (true) {
            $relevance >= 1.0 => 2,
            $relevance > 0.0 => 1,
            default => 0,
        };
    }

    /** Mood's tagMatchComponent() is a continuous matched/selected fraction — bucketed similarly. */
    private static function tierFromMoodFraction(float $fraction): int
    {
        return match (true) {
            $fraction >= 0.67 => 2,
            $fraction > 0.0 => 1,
            default => 0,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $restaurants
     * @return array<int, array{restaurant: array<string, mixed>, distanceKm: float}>
     */
    public function eligibleRestaurants(array $restaurants, array $preference): array
    {
        $eligible = [];
        $funnel = ['checked' => count($restaurants), 'inactive' => 0, 'over_budget' => 0, 'closed' => 0, 'too_far' => 0, 'non_halal' => 0];

        foreach ($restaurants as $restaurant) {
            if (! ($restaurant['is_active'] ?? true)) {
                $funnel['inactive']++;

                continue;
            }
            // null budgetMax means "Anything lah" — no price filter, not "cheapest only".
            if (isset($restaurant['price_level']) && $preference['budgetMax'] !== null
                && $restaurant['price_level'] > $preference['budgetMax']) {
                $funnel['over_budget']++;

                continue;
            }
            // OPEN / CLOSED / UNKNOWN — only CLOSED excludes. UNKNOWN (no opening-hours data) is
            // included unpenalized; missing data isn't evidence a restaurant is unavailable.
            if (($restaurant['open_status'] ?? 'unknown') === 'closed') {
                $funnel['closed']++;

                continue;
            }
            $distanceKm = self::distanceKm(
                $preference['latitude'], $preference['longitude'],
                $restaurant['latitude'], $restaurant['longitude']
            );
            if ($distanceKm > $preference['maxDistanceKm']) {
                $funnel['too_far']++;

                continue;
            }
            // Halal-only hides confirmed non-halal places only — `unknown` stays in (with a
            // "help verify" badge), since missing evidence isn't evidence of non-halal.
            if (($preference['halalOnly'] ?? false) && self::isNonHalal($restaurant)) {
                $funnel['non_halal']++;

                continue;
            }
            $eligible[] = ['restaurant' => $restaurant, 'distanceKm' => $distanceKm];
        }

        $funnel['eligible'] = count($eligible);
        $this->lastFunnel = $funnel;

        return $eligible;
    }

    /** @return array<string, int> */
    public function lastFunnel(): array
    {
        return $this->lastFunnel;
    }

    public function score(array $restaurant, array $preference, float $distanceKm): float
    {
        return $this->scoreBreakdown($restaurant, $preference, $distanceKm)['final'];
    }

    /**
     * Same computation as score(), but returns every component alongside the final number —
     * used directly by score() and separately captured for the winning candidate when
     * RECOMMENDATION_DEBUG is on (see RecommendationController), so debug output can never
     * diverge from what actually scored the pick.
     *
     * @return array{final: float, weights: array<string, int>, components: array<string, float>, relevanceTier: int}
     */
    public function scoreBreakdown(array $restaurant, array $preference, float $distanceKm): array
    {
        $moodTags = $preference['moodTags'] ?? [];
        $cuisines = $preference['cuisines'] ?? [];
        $cravingIntent = $preference['cravingIntent'] ?? null;
        // Broader than isRecognized(): PlacesService's Text Search still runs (via
        // CravingIntent::primarySearchTerm()'s raw-text fallback) even for an unresolved
        // craving, so scoring must still get a chance to credit whatever that search found —
        // gating this on isRecognized() alone would silently discard those candidates' only
        // relevance signal, same failure mode as the original "ice cream -> burger" bug.
        $hasCraving = $cravingIntent instanceof CravingIntent && trim($cravingIntent->raw) !== '';

        // moodTags and craving are mutually exclusive by contract (SoloRecommendationRequest
        // rejects requests with both moods and craving) — whichever is present picks its own
        // weight table.
        $weights = match (true) {
            ! empty($moodTags) => ScoreWeights::forMoodTags(),
            $hasCraving => ScoreWeights::forCraving(),
            default => ScoreWeights::default(),
        };

        $components = [];
        $activeWeights = [];
        // No mood/craving active => everyone is equally "relevant" (tier 2, a no-op gate) and
        // mode/community signals do all the ordering in topCandidates() — exactly where they
        // should matter most.
        $relevanceTier = 2;

        if (! empty($moodTags)) {
            $components['mood'] = self::tagMatchComponent($moodTags, $restaurant['tags'] ?? []);
            $activeWeights[] = [$weights['mood'], $components['mood']];
            $relevanceTier = self::tierFromMoodFraction($components['mood']);
        } elseif ($hasCraving) {
            $components['relevance'] = self::relevanceComponent($cravingIntent, $restaurant);
            $activeWeights[] = [$weights['relevance'], $components['relevance']];
            $relevanceTier = self::tierFromRelevance($components['relevance']);
        }
        if (! empty($cuisines) && isset($weights['cuisine'])) {
            $components['cuisine'] = self::tagMatchComponent($cuisines, $restaurant['cuisines'] ?? []);
            $activeWeights[] = [$weights['cuisine'], $components['cuisine']];
        }
        $components['budget'] = 1.0;
        $activeWeights[] = [$weights['budget'], $components['budget']];
        $components['distance'] = self::distanceComponent($distanceKm, $preference['maxDistanceKm']);
        $activeWeights[] = [$weights['distance'], $components['distance']];
        $components['rating'] = self::ratingComponent($restaurant['rating'] ?? null);
        $activeWeights[] = [$weights['rating'], $components['rating']];

        // DiscoveryMode/Vibe/personal-fit overlays — additive to the same active-weights pool,
        // not a separate table. Only present at all when the caller explicitly opts in (sets
        // these preference keys), so requests/tests that never touch DiscoveryMode get byte-for-
        // byte the same scoring as before this feature existed.
        $mode = $preference['discoveryMode'] ?? null;
        if ($mode !== null) {
            $prior = $preference['communityPrior'] ?? ['success_rate' => 0.5, 'weight' => 10];
            $knownChains = $preference['knownChains'] ?? [];

            foreach (ScoreWeights::discoveryOverlay($mode) as $key => $weight) {
                if ($weight <= 0) {
                    continue;
                }
                $component = match ($key) {
                    'reviewVolumeBonus' => self::reviewVolumeBonusComponent($restaurant['user_rating_count'] ?? null),
                    'cafeRelevance' => self::cafeRelevanceComponent($restaurant),
                    'popularityBonus' => self::popularityBonusComponent($restaurant['user_rating_count'] ?? null),
                    'cheapEatsFit' => self::cheapEatsFitComponent($restaurant),
                    'lateNightFit' => self::lateNightFitComponent($restaurant),
                    'community' => self::communityScoreComponent($restaurant, $prior),
                    'nonChain' => self::nonChainBonusComponent($restaurant['name'] ?? '', $knownChains),
                    default => null,
                };
                if ($component !== null) {
                    $components[$key] = $component;
                    $activeWeights[] = [$weight, $component];
                }
            }
        }

        $vibe = $preference['vibe'] ?? null;
        if ($vibe instanceof Vibe) {
            foreach (ScoreWeights::vibeOverlay($vibe) as $key => $weight) {
                if ($key === 'vibeRelevance') {
                    $components[$key] = self::vibeRelevanceComponent($vibe, $restaurant);
                    $activeWeights[] = [$weight, $components[$key]];
                }
            }
        }

        $brain = $preference['brain'] ?? null;
        $installationHistory = $preference['installationHistory'] ?? null;
        if ($brain instanceof DecisionBrainState) {
            // Makan Brain: Selera Memory + Moment Pulse replace the v1 mode-match personalFit.
            if ($brain->hasPersonalSignal()) {
                $components['personalFit'] = SeleraScorer::fit($brain, $restaurant);
                foreach (ScoreWeights::personalFitOverlay() as $weight) {
                    $activeWeights[] = [$weight, $components['personalFit']];
                }
            }
        } elseif (! empty($installationHistory)) {
            $components['personalFit'] = self::personalFitComponent($restaurant, $installationHistory);
            foreach (ScoreWeights::personalFitOverlay() as $weight) {
                $activeWeights[] = [$weight, $components['personalFit']];
            }
        }

        // Tie-breaker only (see ScoreWeights::halalOverlay()): with halal-only on, verified
        // evidence nudges ordering among otherwise-similar candidates but never beats a
        // better craving/mood match — the relevance tier gate in topCandidates() still rules.
        if ($preference['halalOnly'] ?? false) {
            $components['halalConfidence'] = self::halalConfidenceComponent($restaurant);
            foreach (ScoreWeights::halalOverlay() as $weight) {
                $activeWeights[] = [$weight, $components['halalConfidence']];
            }
        }

        // Keys for every [weight, component] pair so far — v1 pushes anonymous pairs, so rebuild
        // the keyed view from $components (each key's weights summed) for the Decision Trace.
        $weightsByKey = self::keyedWeights($activeWeights, $components, $weights, $mode, $vibe ?? null, $brain, $installationHistory, $preference);

        if ($brain instanceof DecisionBrainState) {
            // Novelty guard: only once there's a history to be novel against (or a lens/tune asks for it).
            $noveltyWeight = 0.0;
            if (SeleraScorer::hasRecent($brain)) {
                $noveltyWeight = (float) config('brain.weights.novelty', 8) * (1 + 0.5 * max(0, min(2, $brain->pulse->noveltyDrive)));
            }
            $noveltyWeight += $brain->extraWeights['novelty'] ?? 0;
            if ($noveltyWeight > 0) {
                $components['novelty'] = SeleraScorer::novelty($brain, $restaurant);
                $activeWeights[] = [$noveltyWeight, $components['novelty']];
                $weightsByKey['novelty'] = ($weightsByKey['novelty'] ?? 0) + $noveltyWeight;
            }

            // Context·confidence, lens and tune overlays — additive weight on named components.
            foreach ($brain->extraWeights as $key => $weight) {
                if ($key === 'novelty' || $weight <= 0) {
                    continue;
                }
                $value = $components[$key] ?? self::componentValue($key, $restaurant, $preference, $distanceKm);
                if ($value === null) {
                    continue;
                }
                $components[$key] = $value;
                $activeWeights[] = [$weight, $value];
                $weightsByKey[$key] = ($weightsByKey[$key] ?? 0) + $weight;
            }

            // A lens can switch a component off entirely (treat_myself → cheapEatsFit).
            foreach ($brain->disabledComponents as $key) {
                unset($weightsByKey[$key]);
            }

            // Brain decisions score straight from the keyed view, so a stored breakdown always
            // reproduces the exact score (Tune / What-if / causal factor depend on that).
            return [
                'final' => self::finalFrom($components, $weightsByKey),
                'weights' => $weights,
                'components' => $components,
                'relevanceTier' => $relevanceTier,
                'activeWeights' => $weightsByKey,
            ];
        }

        $totalWeight = array_sum(array_column($activeWeights, 0));
        $final = 0.0;
        if ($totalWeight > 0) {
            foreach ($activeWeights as [$weight, $component]) {
                $final += ($weight / $totalWeight) * $component * 100;
            }
        }

        return [
            'final' => $final,
            'weights' => $weights,
            'components' => $components,
            'relevanceTier' => $relevanceTier,
            'activeWeights' => $weightsByKey,
        ];
    }

    /**
     * Keyed view of the weights scoreBreakdown() actually applied — rebuilt from the same inputs,
     * so Tune / What-if / causal deciding factor can re-rank stored candidates by arithmetic alone.
     *
     * @return array<string, float>
     */
    private static function keyedWeights(array $activeWeights, array $components, array $weights, $mode, $vibe, $brain, $installationHistory, array $preference): array
    {
        $keyed = [];
        foreach (['mood', 'relevance', 'cuisine', 'budget', 'distance', 'rating'] as $key) {
            if (array_key_exists($key, $components) && isset($weights[$key])) {
                $keyed[$key] = (float) $weights[$key];
            }
        }
        if ($mode !== null) {
            foreach (ScoreWeights::discoveryOverlay($mode) as $key => $weight) {
                if ($weight > 0 && array_key_exists($key, $components)) {
                    $keyed[$key] = ($keyed[$key] ?? 0) + $weight;
                }
            }
        }
        if ($vibe instanceof Vibe && array_key_exists('vibeRelevance', $components)) {
            $keyed['vibeRelevance'] = array_sum(ScoreWeights::vibeOverlay($vibe));
        }
        if (array_key_exists('personalFit', $components)) {
            $keyed['personalFit'] = (float) array_sum(ScoreWeights::personalFitOverlay());
        }
        if (array_key_exists('halalConfidence', $components)) {
            $keyed['halalConfidence'] = (float) array_sum(ScoreWeights::halalOverlay());
        }

        return $keyed;
    }

    /**
     * One component by name, for overlays that weight a component the base table didn't
     * activate (context: rain → distance; tune: cheaper → cheapEatsFit; …). Null = unknown key.
     */
    public static function componentValue(string $key, array $restaurant, array $preference, float $distanceKm): ?float
    {
        return match ($key) {
            'distance' => self::distanceComponent($distanceKm, (float) $preference['maxDistanceKm']),
            'rating' => self::ratingComponent($restaurant['rating'] ?? null),
            'cheapEatsFit' => self::cheapEatsFitComponent($restaurant),
            'lateNightFit' => self::lateNightFitComponent($restaurant),
            'popularityBonus' => self::popularityBonusComponent($restaurant['user_rating_count'] ?? null),
            'reviewVolumeBonus' => self::reviewVolumeBonusComponent($restaurant['user_rating_count'] ?? null),
            'cafeRelevance' => self::cafeRelevanceComponent($restaurant),
            'community' => self::communityScoreComponent($restaurant, $preference['communityPrior'] ?? ['success_rate' => 0.5, 'weight' => 10]),
            'openCertainty' => self::openCertaintyComponent($restaurant),
            'halalConfidence' => self::halalConfidenceComponent($restaurant),
            'novelty' => ($preference['brain'] ?? null) instanceof DecisionBrainState ? SeleraScorer::novelty($preference['brain'], $restaurant) : null,
            default => null,
        };
    }

    /** Open right now = certain; unknown hours = a real risk at Friday prayers / supper / sahur. */
    private static function openCertaintyComponent(array $restaurant): float
    {
        return match ($restaurant['open_status'] ?? 'unknown') {
            'open' => 1.0,
            'closed' => 0.0,
            default => 0.6,
        };
    }

    /**
     * Re-score stored component values under different weights — Tune, What-if and the causal
     * deciding factor all go through this, never through a fresh scoreBreakdown().
     *
     * @param  array<string, float>  $components
     * @param  array<string, float>  $weights
     */
    public static function finalFrom(array $components, array $weights): float
    {
        $total = 0.0;
        $sum = 0.0;
        foreach ($weights as $key => $weight) {
            if ($weight <= 0 || ! array_key_exists($key, $components)) {
                continue;
            }
            $total += $weight;
            $sum += $weight * $components[$key];
        }

        return $total > 0 ? $sum / $total * 100 : 0.0;
    }

    /**
     * Every eligible candidate, scored, breakdown kept, sorted tier-then-score. Makan Brain's
     * entry point — diversity and exploration run over this, not over a pre-truncated top 5.
     *
     * @return array<int, array{restaurant: array, score: float, relevanceTier: int, distanceKm: float, breakdown: array}>
     */
    public function rankAll(array $restaurants, array $preference): array
    {
        $scored = array_map(function ($pair) use ($preference) {
            $breakdown = $this->scoreBreakdown($pair['restaurant'], $preference, $pair['distanceKm']);

            return [
                'restaurant' => $pair['restaurant'],
                'score' => $breakdown['final'],
                'relevanceTier' => $breakdown['relevanceTier'],
                'distanceKm' => $pair['distanceKm'],
                'breakdown' => $breakdown,
            ];
        }, $this->eligibleRestaurants($restaurants, $preference));

        usort($scored, fn ($a, $b) => [$b['relevanceTier'], $b['score']] <=> [$a['relevanceTier'], $a['score']]);

        return $scored;
    }

    /**
     * @param  array<int, array<string, mixed>>  $restaurants
     * @return array<int, array{restaurant: array<string, mixed>, score: float}>
     */
    public function topCandidates(array $restaurants, array $preference, int $limit = 5): array
    {
        $eligible = $this->eligibleRestaurants($restaurants, $preference);

        $scored = array_map(function ($pair) use ($preference) {
            $breakdown = $this->scoreBreakdown($pair['restaurant'], $preference, $pair['distanceKm']);

            return [
                'restaurant' => $pair['restaurant'],
                'score' => $breakdown['final'],
                'relevanceTier' => $breakdown['relevanceTier'],
                'distanceKm' => $pair['distanceKm'],
            ];
        }, $eligible);

        // Tier gates ranking before score gets a say — a candidate that doesn't actually match a
        // recognized craving/mood can never outrank one that does, no matter how strong its
        // mode/community/rating signal is. (No craving/mood active => everyone's tier 2, so this
        // degenerates to a plain score sort, unchanged from before this feature existed.)
        usort($scored, fn ($a, $b) => [$b['relevanceTier'], $b['score']] <=> [$a['relevanceTier'], $a['score']]);

        return array_slice($scored, 0, $limit);
    }

    /**
     * Weighted-random pick over ranked candidates, optionally excluding one (for reroll).
     *
     * @param  array<int, array{restaurant: array<string, mixed>, score: float}>  $candidates
     * @return array{restaurant: array<string, mixed>, score: float}|null
     */
    public function pick(array $candidates, ?array $excluded = null, ?callable $randomSource = null): ?array
    {
        $randomSource ??= fn () => mt_rand() / mt_getrandmax();

        $pool = $candidates;
        if ($excluded !== null) {
            $pool = array_values(array_filter(
                $pool,
                fn ($candidate) => $candidate['restaurant']['id'] !== $excluded['restaurant']['id']
            ));
        }

        if (empty($pool)) {
            return null;
        }

        $weights = array_slice(self::RANK_WEIGHTS, 0, count($pool));
        $totalWeight = array_sum($weights);
        $roll = $randomSource() * $totalWeight;

        foreach ($pool as $index => $candidate) {
            $weight = $weights[$index] ?? 0;
            if ($roll < $weight) {
                return $candidate;
            }
            $roll -= $weight;
        }

        return end($pool);
    }

    /**
     * @param  array<int, array<string, mixed>>  $restaurants
     */
    public function recommend(array $restaurants, array $preference): array
    {
        $candidates = $this->topCandidates($restaurants, $preference);

        return [
            'candidates' => $candidates,
            'pick' => $this->pick($candidates),
            'craving' => $this->cravingMatchStatus($candidates, $preference['cravingIntent'] ?? null),
        ];
    }

    /**
     * @param  array<int, array{restaurant: array<string, mixed>, score: float}>  $candidates
     * @return array{query: string, matched: bool, resolvedAs: ?string, source: string, confidence: float}|null
     */
    public function cravingMatchStatus(array $candidates, ?CravingIntent $intent): ?array
    {
        if ($intent === null || trim($intent->raw) === '') {
            return null;
        }

        $matched = false;
        foreach ($candidates as $candidate) {
            if (self::relevanceComponent($intent, $candidate['restaurant']) > 0) {
                $matched = true;
                break;
            }
        }

        return [
            'query' => $intent->raw,
            'matched' => $matched,
            'resolvedAs' => $intent->concept,
            'source' => $intent->source,
            'confidence' => $intent->confidence,
        ];
    }
}
