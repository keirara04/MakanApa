<?php

namespace App\Filament\Pages;

use App\Services\Brain\BrainEvaluationReport;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Url;

/**
 * Is Makan Brain actually better than v1 — and for whom? Cohorted, so a big win for returning
 * users can't be hidden by (or hide) a flat result for brand-new ones. Both reports aggregate
 * over every decision in the window, so they're cached for 10 minutes per cohort/window.
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
        $days = max(1, min(180, $this->days));
        $cohort = array_key_exists($this->cohort, BrainEvaluationReport::COHORTS) ? $this->cohort : 'all';

        return Cache::remember("admin-page:brain-insights:metrics:{$days}:{$cohort}", now()->addMinutes(10), fn () => app(BrainEvaluationReport::class)->metrics($days, $cohort));
    }

    public function concentration(): array
    {
        $days = max(1, min(180, $this->days));

        return Cache::remember("admin-page:brain-insights:concentration:{$days}", now()->addMinutes(10), fn () => app(BrainEvaluationReport::class)->concentration($days));
    }
}
