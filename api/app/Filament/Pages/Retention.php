<?php

namespace App\Filament\Pages;

use App\Services\Analytics\RetentionReport;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

/** Weekly signup cohorts and how many came back on day 1/7/30 and in weeks 1–4 (RetentionReport). */
class Retention extends Page
{
    protected static \UnitEnum|string|null $navigationGroup = 'Overview';

    protected static ?string $navigationLabel = 'Retention';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static ?string $slug = 'analytics/retention';

    protected string $view = 'filament.pages.retention';

    #[Url]
    public string $segment = 'all';

    #[Url]
    public int $weeks = 8;

    public function getTitle(): string
    {
        return 'Retention by signup week';
    }

    /** @return array<string, string> */
    public function segments(): array
    {
        return RetentionReport::SEGMENTS;
    }

    /** @return list<array<string, mixed>> */
    public function cohorts(): array
    {
        return app(RetentionReport::class)->cohorts($this->weeks, $this->segment);
    }
}
