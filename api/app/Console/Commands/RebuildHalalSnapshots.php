<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use App\Services\Halal\HalalSnapshotService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * restaurant_halal_verifications is the source of truth; restaurants.halal_* is a rebuildable
 * snapshot. Run this if the snapshot ever drifts (or after a manual data fix in the ledger).
 */
#[Signature('halal:rebuild-snapshots')]
#[Description('Recompute restaurants.halal_* from the halal verification ledger')]
class RebuildHalalSnapshots extends Command
{
    public function handle(HalalSnapshotService $snapshots): int
    {
        $count = 0;

        Restaurant::query()->orderBy('id')->chunkById(500, function ($restaurants) use ($snapshots, &$count) {
            foreach ($restaurants as $restaurant) {
                $snapshots->rebuild($restaurant);
                $count++;
            }
        });

        $this->info("Rebuilt halal snapshots for {$count} restaurants.");

        return self::SUCCESS;
    }
}
