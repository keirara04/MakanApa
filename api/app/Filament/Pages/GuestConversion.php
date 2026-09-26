<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\GuestConversionTrendChart;
use App\Services\Analytics\GuestConversionReport;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

/**
 * Are guests turning into accounts, and which prompt did it? Reads users.is_guest /
 * upgraded_from_guest_at / signup_source (see GuestConversionReport).
 */
class GuestConversion extends Page
{
    protected static \UnitEnum|string|null $navigationGroup = 'Overview';

    protected static ?string $navigationLabel = 'Guest conversion';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingUp;

    protected static ?string $slug = 'analytics/guest-conversion';

    protected string $view = 'filament.pages.guest-conversion';

    #[Url]
    public int $days = 30;

    public function getTitle(): string
    {
        return 'Guest → account conversion';
    }

    protected function getHeaderWidgets(): array
    {
        return [GuestConversionTrendChart::class];
    }

    public function windowDays(): int
    {
        return in_array($this->days, [7, 30, 90], true) ? $this->days : 30;
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        return app(GuestConversionReport::class)->summary($this->windowDays());
    }
}
