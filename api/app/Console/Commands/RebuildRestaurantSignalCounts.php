<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recovery path documented in the DiscoveryMode plan: decision_recommendations is the canonical
 * source of impressions/accepted/rejected — restaurants.*_count are just a rebuildable
 * denormalization for fast scoring reads. Run this if those counters ever drift.
 */
#[Signature('restaurants:rebuild-signal-counts')]
#[Description('Recompute restaurants.impressions_count/accepted_count/rejected_count from decision_recommendations')]
class RebuildRestaurantSignalCounts extends Command
{
    public function handle(): int
    {
        Restaurant::query()->update([
            'impressions_count' => 0,
            'accepted_count' => 0,
            'rejected_count' => 0,
        ]);

        $counts = DB::table('decision_recommendations')
            ->selectRaw('restaurant_id')
            ->selectRaw('count(*) filter (where shown_at is not null) as impressions')
            ->selectRaw('count(*) filter (where accepted_at is not null) as accepted')
            ->selectRaw('count(*) filter (where rejected_at is not null) as rejected')
            ->groupBy('restaurant_id')
            ->get();

        foreach ($counts as $row) {
            Restaurant::whereKey($row->restaurant_id)->update([
                'impressions_count' => $row->impressions,
                'accepted_count' => $row->accepted,
                'rejected_count' => $row->rejected,
            ]);
        }

        $this->info("Rebuilt signal counts for {$counts->count()} restaurants.");

        return self::SUCCESS;
    }
}
