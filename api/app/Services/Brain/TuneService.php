<?php

namespace App\Services\Brain;

use App\Models\Decision;
use App\Models\DecisionRecommendation;
use App\Models\Restaurant;
use App\Models\User;
use App\Support\Halal\HalalStatus;
use App\Support\RecommendationHeadline;
use App\Support\TuneDirection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * "Not quite? Closer / Cheaper / Safer bet / More adventurous" — reranks the *stored* pool with
 * extra weight on one component, composing with earlier tunes on the same decision. Pure
 * arithmetic over ≤8 persisted breakdowns: no Places call, no re-scoring. Capped per decision
 * so it can't pretend a tiny pool is infinite; past the cap (or when nothing in the pool
 * actually moves in that direction) it offers to search wider instead.
 */
class TuneService
{
    private const LABELS = [
        'closer' => 'closer',
        'cheaper' => 'cheaper',
        'safer' => 'safer bet',
        'adventurous' => 'more adventurous',
    ];

    public function __construct(private readonly TasteEventRecorder $recorder) {}

    /**
     * @return array{row: ?DecisionRecommendation, lead: ?string, searchWider: ?array}
     */
    public function tune(Decision $decision, TuneDirection $direction, ?User $user, bool $halalOnly): array
    {
        $tunes = $decision->tunes ?? [];
        if (count($tunes) >= (int) Config::get('brain.tune.max_per_decision', 2)) {
            return ['row' => null, 'lead' => null, 'searchWider' => $this->searchWider($decision, $direction)];
        }

        $result = DB::transaction(function () use ($decision, $direction, $tunes, $halalOnly) {
            $rows = $decision->recommendations()->with('restaurant')->orderBy('score_rank')->lockForUpdate()->get();
            $current = $rows->first(fn ($r) => $r->shown_at !== null && $r->rejected_at === null && $r->accepted_at === null);
            if (! $current || ! $current->breakdown) {
                return null;
            }

            $newTunes = [...$tunes, $direction->value];
            $overlay = [];
            foreach ($newTunes as $tune) {
                foreach (Config::get("brain.tune.directions.{$tune}.weights", []) as $key => $weight) {
                    $overlay[$key] = ($overlay[$key] ?? 0) + $weight;
                }
            }

            $pool = $rows->values()->map(fn (DecisionRecommendation $row) => [
                'row' => $row,
                'id' => $row->restaurant_id,
                'name' => $row->breakdown['facts']['name'] ?? $row->restaurant?->name,
                'tier' => (int) ($row->breakdown['tier'] ?? 2),
                'components' => $row->breakdown['components'] ?? [],
                'weights' => $row->breakdown['weights'] ?? [],
                'facts' => $row->breakdown['facts'] ?? [],
            ])->all();

            $currentFacts = $current->breakdown['facts'];
            $currentComponents = $current->breakdown['components'];

            $index = Counterfactual::winner(
                $pool,
                function (array $weights, array $candidate) use ($overlay) {
                    foreach ($overlay as $key => $weight) {
                        if (array_key_exists($key, $candidate['components'])) {
                            $weights[$key] = ($weights[$key] ?? 0) + $weight;
                        }
                    }

                    return $weights;
                },
                fn (array $candidate) => $candidate['row']->rejected_at === null
                    && $candidate['row']->id !== $current->id
                    && ! ($halalOnly && $candidate['row']->restaurant?->effectiveHalalStatus() === HalalStatus::NonHalal)
                    && self::improves($direction, $candidate, $currentFacts, $currentComponents),
            );

            if ($index === null) {
                return null;
            }

            $next = $pool[$index]['row'];
            $current->update(['rejected_at' => now(), 'reject_reason' => 'tune_'.$direction->value]);
            $next->update(['shown_at' => now()]);
            $decision->update(['tunes' => $newTunes]);
            Restaurant::whereKey($current->restaurant_id)->increment('rejected_count');
            Restaurant::whereKey($next->restaurant_id)->increment('impressions_count');

            return [$current, $next, $newTunes];
        });

        if ($result === null) {
            return ['row' => null, 'lead' => null, 'searchWider' => $this->searchWider($decision, $direction)];
        }

        [$current, $next, $newTunes] = $result;
        $this->recorder->tune($decision, $current, $user, $direction);

        return ['row' => $next, 'lead' => self::lead($newTunes, $next->breakdown['facts'] ?? []), 'searchWider' => null];
    }

    /** Does this candidate actually move in the asked direction vs what's shown now? */
    private static function improves(TuneDirection $direction, array $candidate, array $current, array $currentComponents): bool
    {
        $f = $candidate['facts'];
        $c = $candidate['components'];

        return match ($direction) {
            TuneDirection::Closer => ($f['distanceKm'] ?? INF) < ($current['distanceKm'] ?? INF),
            TuneDirection::Cheaper => ($f['priceLevel'] !== null && $current['priceLevel'] !== null)
                ? $f['priceLevel'] < $current['priceLevel']
                : ($c['cheapEatsFit'] ?? 0) > ($currentComponents['cheapEatsFit'] ?? 0),
            TuneDirection::Safer => ($f['rating'] ?? 0) >= ($current['rating'] ?? 0)
                && ($f['userRatingCount'] ?? 0) >= ($current['userRatingCount'] ?? 0)
                && ($f['openStatus'] ?? 'unknown') !== 'closed',
            TuneDirection::Adventurous => ($f['category'] ?? null) !== ($current['category'] ?? null)
                && ($c['novelty'] ?? 1) >= ($currentComponents['novelty'] ?? 1),
        };
    }

    /** "Cheaper + closer — found one 500 m away for under RM15." */
    public static function lead(array $tunes, array $facts): string
    {
        $labels = array_map(fn ($t) => self::LABELS[$t] ?? $t, $tunes);
        $head = ucfirst(implode(' + ', $labels));
        $meters = (int) round(($facts['distanceKm'] ?? 0) * 1000);
        $distance = $meters < 1000 ? (int) (round($meters / 50) * 50).' m' : sprintf('%.1f km', $meters / 1000);
        $price = match ($facts['priceLevel'] ?? null) {
            0, 1 => 'under RM15',
            2 => 'around RM15–30',
            3 => 'around RM30–60',
            4 => 'RM60 and up',
            default => null,
        };

        $detail = match (end($tunes)) {
            'closer' => "only {$distance} away",
            'cheaper' => $price ? "found one {$distance} away for {$price}" : "found one {$distance} away that's easier on the wallet",
            'safer' => isset($facts['rating']) ? sprintf('%.1f★, a proven spot', $facts['rating']) : 'a proven spot',
            'adventurous' => ($cat = RecommendationHeadline::categoryLabel($facts['category'] ?? null)) ? 'something new: '.mb_strtolower($cat) : 'something new',
            default => $distance,
        };

        return "{$head} — {$detail}";
    }

    private function searchWider(Decision $decision, TuneDirection $direction): array
    {
        $current = (float) ($decision->max_distance ?? 2);
        $wider = min((float) Config::get('brain.tune.search_wider_max_km', 10), $current * (float) Config::get('brain.tune.search_wider_factor', 1.75));

        return [
            'canSearchWider' => $wider > $current + 0.1,
            'message' => 'Nothing better in these options. Search a bit wider?',
            'suggestedAdjustment' => [
                'distanceKm' => round($wider * 2) / 2,
                'budgetMax' => $direction === TuneDirection::Cheaper ? max(1, ((int) ($decision->budget_max ?? 2)) - 1) : $decision->budget_max,
            ],
        ];
    }
}
