<?php

namespace App\Console\Commands;

use App\Models\AiJudgment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Clears old state snapshots; rows, hashes, answers and outcomes are kept for calibration. */
#[Signature('judgments:prune')]
#[Description('Clear ai_judgments state snapshots older than judgment.state_retention_days')]
class PruneJudgmentStates extends Command
{
    public function handle(): int
    {
        $count = AiJudgment::whereNotNull('state')
            ->where('created_at', '<', now()->subDays((int) config('judgment.state_retention_days', 90)))
            ->update(['state' => null]);

        $this->info("Cleared {$count} state snapshot(s).");

        return self::SUCCESS;
    }
}
