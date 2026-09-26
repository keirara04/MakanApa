<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\UserAccountDeletionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Guest accounts (POST auth/guest) are minted per install and abandoned whenever the app is
 * deleted or the person signs in to an existing account, so without this they pile up forever.
 * A guest is stale once it's older than the window and none of its tokens has been used (or,
 * if never used, created) within it — tokens expire at 90 days, so a live guest always has one.
 * Goes through UserAccountDeletionService so a guest leaves exactly what a deleted account does.
 */
#[Signature('users:prune-guests')]
#[Description('Delete guest accounts with no activity in the last 90 days')]
class PruneStaleGuests extends Command
{
    private const STALE_AFTER_DAYS = 90;

    public function handle(UserAccountDeletionService $deletion): int
    {
        $cutoff = now()->subDays(self::STALE_AFTER_DAYS);
        $count = 0;

        User::query()
            ->where('is_guest', true)
            ->where('created_at', '<', $cutoff)
            ->whereDoesntHave('tokens', fn ($tokens) => $tokens
                ->where('last_used_at', '>=', $cutoff)
                ->orWhere(fn ($neverUsed) => $neverUsed->whereNull('last_used_at')->where('created_at', '>=', $cutoff)))
            ->lazyById()
            ->each(function (User $guest) use ($deletion, &$count) {
                $deletion->delete($guest);
                $count++;
            });

        $this->info("Deleted {$count} stale guest account(s).");

        return self::SUCCESS;
    }
}
