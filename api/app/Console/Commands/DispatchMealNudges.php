<?php

namespace App\Console\Commands;

use App\Services\Nudges\MealNudgeDispatcher;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Sends mealtime nudges that are due — opt-in, at most one a day per user. Scheduled every five
 * minutes (routes/console.php); needs the scheduler + queue worker running in production.
 */
class DispatchMealNudges extends Command
{
    protected $signature = 'nudges:dispatch {--dry-run : Show who would get what, without sending or changing state}';

    protected $description = 'Send mealtime nudges that are due (opt-in, max one a day per user).';

    public function handle(MealNudgeDispatcher $dispatcher): int
    {
        $results = $dispatcher->dispatch(CarbonImmutable::now(), dryRun: (bool) $this->option('dry-run'));

        if ($results === []) {
            $this->info('No nudges due.');

            return self::SUCCESS;
        }

        $this->table(['User', 'Outcome', 'Detail'], array_map(fn (array $r) => [$r['user_id'], $r['outcome'], $r['detail']], $results));

        return self::SUCCESS;
    }
}
