<?php

namespace App\Services\Judgment\Definitions;

use App\Models\Restaurant;
use App\Models\RestaurantMenuItem;
use App\Services\Judgment\JudgmentDefinition;
use App\Services\Judgment\Questions\BinaryQuestion;
use App\Services\Judgment\StateTrimmer;
use App\Support\Halal\HalalHeuristicResult;

/**
 * Second opinion for places the keyword heuristic only weakly matched ("wine", "bar", "pub").
 * Output is an ADMIN REVIEW HINT only — a false non-halal hides a place from every halal-only
 * user, so this never writes to the halal ledger or snapshot.
 */
final class NonHalalSecondOpinionV1 extends JudgmentDefinition
{
    public function purpose(): string
    {
        return 'non_halal_second_opinion';
    }

    public function version(): int
    {
        return 1;
    }

    /** Re-judge when the listing itself changes (renamed, new menu). */
    public function reevaluatesOnStateChange(): bool
    {
        return true;
    }

    public function questions(): array
    {
        return [
            new BinaryQuestion(
                'likely_serves_pork_or_alcohol',
                'Based on `evidence.listing`, is this place likely to serve pork, lard, or alcoholic drinks?',
                yesMeans: 'The listing itself points to pork/lard/alcohol being served — e.g. a wine bar, pub, brewery or bar that serves alcohol; pork dishes such as char siu or bak kut teh on the menu.',
                noMeans: 'The listing does not point to pork/alcohol — e.g. a café merely named "Wine & Dine" with a halal-style menu, a Malay/Indian-Muslim/Middle-Eastern eatery, or a mocktail/juice "bar".',
                samples: 3,
            ),
        ];
    }

    /** @param array{restaurant: Restaurant, heuristic: HalalHeuristicResult} $context */
    public function buildState(array $context): array
    {
        $r = $context['restaurant'];
        $menu = RestaurantMenuItem::where('restaurant_id', $r->id)->orderBy('sort_order')
            ->limit($this->limit('max_menu_items'))->pluck('name')->all();

        return [
            'evidence' => ['listing' => array_filter([
                'name' => StateTrimmer::text($r->name, 120),
                'google_types' => StateTrimmer::list(NonHalalSecondOpinionGate::specificTypes($r->google_types ?? []), $this->limit('max_list_items')),
                'category' => $r->food_category,
                'cuisines' => $r->cuisines()->pluck('slug')->take($this->limit('max_list_items'))->values()->all(),
                'signature_dish' => StateTrimmer::text($r->signature_dish, 120),
                'menu_items' => array_map(fn ($n) => StateTrimmer::text($n, 80), $menu),
            ], fn ($v) => $v !== null && $v !== [])],
            'context' => ['keyword_check' => array_map(
                fn ($m) => "{$m['field']}: {$m['term']} ({$m['strength']})",
                StateTrimmer::list($context['heuristic']->matches, $this->limit('max_list_items')),
            )],
        ];
    }

    /** Truth = what an admin/community decision later settled on (unknown carries no label). */
    public function calibrationTargets(array $outcome): array
    {
        $status = $outcome['status'] ?? null;
        if (! in_array($status, ['certified', 'muslim_friendly', 'non_halal'], true)) {
            return [];
        }

        return ['likely_serves_pork_or_alcohol' => $status === 'non_halal'];
    }
}
