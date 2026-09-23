<?php

namespace App\Console\Commands;

use App\Jobs\SecondOpinionNonHalal;
use App\Models\Restaurant;
use App\Services\Halal\HalalVerificationService;
use App\Services\Judgment\Definitions\NonHalalSecondOpinionGate;
use App\Support\Halal\HalalHeuristic;
use App\Support\Halal\HalalStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Backfill for the conservative non-halal heuristic. Always run with --dry-run first and read
 * the reasons: a false positive hides a restaurant from every halal-only user. Never touches
 * human-reviewed decisions (HalalDecisionPolicy).
 */
#[Signature('halal:classify {--dry-run : Print what would be flagged without writing}')]
#[Description('Run the non-halal heuristic over every restaurant and record automatic verifications')]
class ClassifyRestaurantsHalal extends Command
{
    public function handle(HalalVerificationService $verifications): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $flagged = 0;
        $recorded = 0;

        Restaurant::query()->whereNull('merged_into_restaurant_id')->orderBy('id')->chunkById(500, function ($restaurants) use ($dryRun, $verifications, &$flagged, &$recorded) {
            foreach ($restaurants as $restaurant) {
                $result = HalalHeuristic::evaluate([
                    'name' => $restaurant->name,
                    'signature_dish' => $restaurant->signature_dish,
                    'food_category' => $restaurant->food_category,
                    'google_types' => $restaurant->google_types,
                ]);

                if ($result->likelyNonHalal) {
                    $flagged++;
                    $this->line("#{$restaurant->id} {$restaurant->name} — {$result->describe()} → non_halal");
                } elseif ($result->matches !== []) {
                    $this->line("<comment>#{$restaurant->id} {$restaurant->name} — weak only ({$result->describe()}) → left unknown</comment>");
                }

                if (! $dryRun && ($result->likelyNonHalal || $restaurant->halal_status === HalalStatus::NonHalal)) {
                    $recorded += $verifications->recordHeuristic($restaurant, $result) !== null ? 1 : 0;
                } elseif (NonHalalSecondOpinionGate::shouldAsk($restaurant, $result)) {
                    $this->line('<comment>   → AI second opinion '.($dryRun ? 'would be queued' : 'queued').'</comment>');
                    if (! $dryRun) {
                        SecondOpinionNonHalal::dispatchIfDue($restaurant);
                    }
                }
            }
        });

        $this->info($dryRun
            ? "Dry run: {$flagged} restaurant(s) would be flagged non_halal."
            : "Flagged {$flagged}; recorded {$recorded} new automatic verification(s) (human-reviewed rows skipped).");

        return self::SUCCESS;
    }
}
