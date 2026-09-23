<?php

namespace App\Console\Commands;

use App\Models\TasteEvent;
use App\Services\Brain\TasteOwner;
use App\Services\Brain\TasteProfileBuilder;
use Illuminate\Console\Command;

/**
 * taste_profiles is a materialized view of taste_events — this rebuilds it from the log (after
 * each owner's latest reset). Deterministic: running it twice yields identical profiles.
 */
class BrainRebuildTaste extends Command
{
    protected $signature = 'brain:rebuild-taste {--user= : Only this user id} {--installation= : Only this anonymous installation} {--all : Every owner with events}';

    protected $description = 'Rebuild Makan Brain Selera profiles from the taste_events log';

    public function handle(TasteProfileBuilder $builder): int
    {
        $owners = match (true) {
            $this->option('user') !== null => [new TasteOwner((int) $this->option('user'), null)],
            $this->option('installation') !== null => [new TasteOwner(null, (string) $this->option('installation'))],
            (bool) $this->option('all') => $this->allOwners(),
            default => null,
        };

        if ($owners === null) {
            $this->error('Pass --user=, --installation= or --all.');

            return self::INVALID;
        }

        $bar = $this->output->createProgressBar(count($owners));
        foreach ($owners as $owner) {
            $builder->rebuild($owner);
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->info('Rebuilt '.count($owners).' profile(s).');

        return self::SUCCESS;
    }

    /** @return TasteOwner[] */
    private function allOwners(): array
    {
        $users = TasteEvent::query()->whereNotNull('user_id')->distinct()->pluck('user_id')
            ->map(fn ($id) => new TasteOwner((int) $id, null));
        $installations = TasteEvent::query()->whereNull('user_id')->whereNotNull('installation_id')->distinct()->pluck('installation_id')
            ->map(fn ($id) => new TasteOwner(null, $id));

        return [...$users->all(), ...$installations->all()];
    }
}
