<?php

namespace App\Filament\Pages;

use App\Services\Brain\BrainEvaluationReport;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

/**
 * Is Makan Brain actually better than v1 — and for whom? Cohorted, so a big win for returning
 * users can't be hidden by (or hide) a flat result for brand-new ones.
 */
class MakanBrainInsights extends Page
{
    protected static \UnitEnum|string|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Makan Brain';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $slug = 'brain/insights';

    protected string $view = 'filament.pages.makan-brain-insights';

    #[Url]
    public string $cohort = 'all';

    #[Url]
    public int $days = 30;

    public function getTitle(): string
    {
        return 'Makan Brain · v1 vs v2';
    }

    public function cohorts(): array
    {
        return BrainEvaluationReport::COHORTS;
    }

    public function metrics(): array
    {
        return app(BrainEvaluationReport::class)->metrics(max(1, min(180, $this->days)), array_key_exists($this->cohort, BrainEvaluationReport::COHORTS) ? $this->cohort : 'all');
    }

    public function concentration(): array
    {
        return app(BrainEvaluationReport::class)->concentration(max(1, min(180, $this->days)));
    }
}
