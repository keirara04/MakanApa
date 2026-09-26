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

        // Search choices are 100% accepted by construction — they'd flatter whichever version they
        // landed under, so they never count toward the v1-vs-v2 comparison.
        $base = fn () => $this->cohort(DB::table('decisions')->where('decisions.created_at', '>=', $since)->where('decisions.mode', '!=', 'search'), $cohort);

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

    /**
     * Is the brain concentrating everyone onto the same few places? Per algorithm version.
     * Aggregated in Postgres (window functions) rather than pulling every accepted pick into
     * PHP: top-10 share, coverage, distinct categories, and how often a person's next accept
     * repeats their previous one's category (consecutive pairs per user, else per install).
     */
    public function concentration(int $days): array
    {
        $activeRestaurants = max(1, Restaurant::where('is_active', true)->count());

        $rows = DB::select(<<<'SQL'
            with accepted as (
                select decisions.algorithm_version as version, r.id, r.restaurant_id, r.accepted_at,
                       nullif(restaurants.food_category, '') as food_category,
                       coalesce(decisions.user_id::text, decisions.installation_id, '') as owner
                from decision_recommendations as r
                join decisions on decisions.id = r.decision_id
                join restaurants on restaurants.id = r.restaurant_id
                where r.accepted_at is not null and r.accepted_at >= ?
            ),
            per_restaurant as (
                select version, count(*) as picks,
                       row_number() over (partition by version order by count(*) desc) as rank
                from accepted
                group by version, restaurant_id
            ),
            sequenced as (
                select version, food_category,
                       lag(food_category) over (partition by version, owner order by accepted_at, id) as previous_category,
                       row_number() over (partition by version, owner order by accepted_at, id) as position
                from accepted
            )
            select totals.version, totals.accepts, totals.restaurants, totals.categories, top.top10, pairs.pairs, pairs.repeats
            from (
                select version, count(*) as accepts, count(distinct restaurant_id) as restaurants, count(distinct food_category) as categories
                from accepted group by version
            ) as totals
            join (
                select version, sum(picks) filter (where rank <= 10) as top10 from per_restaurant group by version
            ) as top on top.version is not distinct from totals.version
            join (
                select version,
                       count(*) filter (where position > 1) as pairs,
                       count(*) filter (where position > 1 and food_category is not null and food_category = previous_category) as repeats
                from sequenced group by version
            ) as pairs on pairs.version is not distinct from totals.version
            order by totals.version
            SQL, [now()->subDays($days)]);

        return array_map(fn (object $row) => [
            'version' => $row->version,
            'accepts' => (int) $row->accepts,
            'top10Share' => (int) $row->top10 / max(1, (int) $row->accepts),
            'coverage' => (int) $row->restaurants / $activeRestaurants,
            'categories' => (int) $row->categories,
            'repeatCategoryRate' => (int) $row->pairs > 0 ? (int) $row->repeats / (int) $row->pairs : null,
        ], $rows);
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
