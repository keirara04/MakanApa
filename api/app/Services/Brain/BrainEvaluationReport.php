<?php

namespace App\Services\Brain;

use App\Models\Restaurant;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * v1 vs Makan Brain, per cohort — grouped queries only. Accept rate alone hides a lot, so this
 * also tracks decision speed, rerolls/tunes, fatigue, regret (accepted, then decided again
 * within 2 minutes or reopened) and whether exploratory picks land, plus concentration
 * (is personalization quietly killing discovery?).
 */
class BrainEvaluationReport
{
    public const COHORTS = [
        'all' => 'Everyone',
        'solo' => 'Decide (solo)',
        'nearby' => 'Nearby pick',
        'halal' => 'Halal-only',
        'returning' => 'Returning (3+ earlier decisions)',
        'new' => 'New (fewer than 3 earlier decisions)',
        'craving' => 'Explicit craving (v2 only)',
        'anything' => 'Anything / no intent (v2 only)',
        'selera_known' => 'Selera knowing/strong (v2 only)',
    ];

    public function metrics(int $days, string $cohort): array
    {
        $since = now()->subDays($days);

        $base = fn () => $this->cohort(DB::table('decisions')->where('decisions.created_at', '>=', $since), $cohort);

        $rows = $base()
            ->leftJoin('decision_recommendations as acc', function ($join) {
                $join->on('acc.decision_id', '=', 'decisions.id')->whereNotNull('acc.accepted_at');
            })
            ->groupBy('decisions.algorithm_version')
            ->selectRaw('decisions.algorithm_version as version')
            ->selectRaw('count(distinct decisions.id) as decisions')
            ->selectRaw('count(distinct acc.decision_id) as accepted')
            ->selectRaw('percentile_cont(0.5) within group (order by extract(epoch from (acc.accepted_at - decisions.created_at))) as median_seconds')
            ->selectRaw('avg(case when decisions.fatigue_mode then 1.0 else 0.0 end) as fatigue_rate')
            ->get()->keyBy('version');

        $actions = $base()
            ->join('decision_recommendations as r', 'r.decision_id', '=', 'decisions.id')
            ->whereNotNull('r.rejected_at')
            ->groupBy('decisions.algorithm_version')
            ->selectRaw('decisions.algorithm_version as version')
            ->selectRaw("sum(case when r.reject_reason like 'tune_%' then 1 else 0 end) as tunes")
            ->selectRaw("sum(case when r.reject_reason is null or r.reject_reason not like 'tune_%' then 1 else 0 end) as rerolls")
            ->get()->keyBy('version');

        $interactions = $base()
            ->join('decision_interactions as i', 'i.decision_id', '=', 'decisions.id')
            ->groupBy('decisions.algorithm_version', 'i.type')
            ->selectRaw('decisions.algorithm_version as version, i.type, count(distinct i.decision_id) as n')
            ->get()->groupBy('version');

        $regret = $base()
            ->join('decision_recommendations as acc', function ($join) {
                $join->on('acc.decision_id', '=', 'decisions.id')->whereNotNull('acc.accepted_at');
            })
            ->groupBy('decisions.algorithm_version')
            ->selectRaw('decisions.algorithm_version as version')
            ->selectRaw("count(distinct case when exists (
                    select 1 from decisions d2
                    where d2.id > decisions.id
                      and coalesce(d2.installation_id, '') = coalesce(decisions.installation_id, '')
                      and coalesce(d2.user_id, 0) = coalesce(decisions.user_id, 0)
                      and d2.created_at between acc.accepted_at and acc.accepted_at + interval '2 minutes'
                ) or exists (
                    select 1 from decision_interactions di where di.decision_id = decisions.id and di.type = 'reopened'
                ) then decisions.id end) as regretted")
            ->get()->keyBy('version');

        $exploration = $base()
            ->join('decision_recommendations as r', 'r.decision_id', '=', 'decisions.id')
            ->where('r.selected', true)
            ->where('r.selection_probability', '<', 0.2)
            ->groupBy('decisions.algorithm_version')
            ->selectRaw('decisions.algorithm_version as version, count(*) as shown, sum(case when r.accepted_at is not null then 1 else 0 end) as accepted')
            ->get()->keyBy('version');

        return $rows->map(function ($row) use ($actions, $interactions, $regret, $exploration) {
            $n = max(1, (int) $row->decisions);
            $types = collect($interactions->get($row->version, []))->pluck('n', 'type');
            $explore = $exploration->get($row->version);

            return [
                'version' => $row->version,
                'decisions' => (int) $row->decisions,
                'acceptRate' => $row->accepted / $n,
                'medianSeconds' => $row->median_seconds !== null ? (float) $row->median_seconds : null,
                'rerollsPerDecision' => (int) ($actions->get($row->version)->rerolls ?? 0) / $n,
                'tunesPerDecision' => (int) ($actions->get($row->version)->tunes ?? 0) / $n,
                'fatigueRate' => (float) $row->fatigue_rate,
                'regretRate' => $row->accepted > 0 ? (int) ($regret->get($row->version)->regretted ?? 0) / $row->accepted : 0.0,
                'exploratoryShown' => (int) ($explore->shown ?? 0),
                'exploratoryAcceptRate' => ($explore->shown ?? 0) > 0 ? $explore->accepted / $explore->shown : null,
                'reasonsExpandedRate' => ($types['reasons_expanded'] ?? 0) / $n,
                'whatIfRate' => ($types['what_if_opened'] ?? 0) / $n,
                'directionsRate' => ($types['directions_opened'] ?? 0) / $n,
            ];
        })->values()->all();
    }

    /** Is the brain concentrating everyone onto the same few places? Per algorithm version. */
    public function concentration(int $days): array
    {
        $since = now()->subDays($days);
        $activeRestaurants = max(1, Restaurant::where('is_active', true)->count());

        $accepted = DB::table('decision_recommendations as r')
            ->join('decisions', 'decisions.id', '=', 'r.decision_id')
            ->join('restaurants', 'restaurants.id', '=', 'r.restaurant_id')
            ->whereNotNull('r.accepted_at')
            ->where('r.accepted_at', '>=', $since)
            ->select('decisions.algorithm_version as version', 'r.restaurant_id', 'restaurants.food_category', 'decisions.user_id', 'decisions.installation_id', 'r.accepted_at')
            ->get()
            ->groupBy('version');

        return $accepted->map(function ($rows, $version) use ($activeRestaurants) {
            $perRestaurant = $rows->countBy('restaurant_id')->sortDesc();
            $total = max(1, $rows->count());

            $repeats = 0;
            $pairs = 0;
            foreach ($rows->groupBy(fn ($r) => $r->user_id ?? $r->installation_id)->filter(fn ($g) => $g->count() >= 2) as $group) {
                $sorted = $group->sortBy('accepted_at')->values();
                for ($i = 1; $i < $sorted->count(); $i++) {
                    $pairs++;
                    $repeats += $sorted[$i]->food_category !== null && $sorted[$i]->food_category === $sorted[$i - 1]->food_category ? 1 : 0;
                }
            }

            return [
                'version' => $version,
                'accepts' => $rows->count(),
                'top10Share' => $perRestaurant->take(10)->sum() / $total,
                'coverage' => $perRestaurant->count() / $activeRestaurants,
                'categories' => $rows->pluck('food_category')->filter()->unique()->count(),
                'repeatCategoryRate' => $pairs > 0 ? $repeats / $pairs : null,
            ];
        })->values()->all();
    }

    private function cohort(Builder $query, string $cohort): Builder
    {
        $prior = '(select count(*) from decisions p where p.id < decisions.id and (
                    (decisions.user_id is not null and p.user_id = decisions.user_id)
                 or (decisions.user_id is null and p.installation_id = decisions.installation_id)))';

        return match ($cohort) {
            'solo' => $query->where('decisions.mode', 'solo'),
            'nearby' => $query->where('decisions.mode', 'nearby'),
            'halal' => $query->where('decisions.halal_only', true),
            'returning' => $query->whereRaw("{$prior} >= 3"),
            'new' => $query->whereRaw("{$prior} < 3"),
            'craving' => $query->where('decisions.intent_type', 'craving'),
            'anything' => $query->where('decisions.intent_type', 'anything'),
            'selera_known' => $query->whereIn('decisions.selera_stage', ['knowing', 'strong']),
            default => $query,
        };
    }
}
