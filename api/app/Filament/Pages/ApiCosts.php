<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ApiUsageChart;
use App\Services\Analytics\ApiCostReport;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Estimated month-to-date spend on Google Places and OpenRouter against the budgets in
 * config/admin_budgets.php, with a straight-line month-end projection. `admin:check-api-budget`
 * emails/notifies superadmins when a budget threshold is crossed.
 */
class ApiCosts extends Page
{
    protected static \UnitEnum|string|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'API costs';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static ?string $slug = 'api-costs';

    protected string $view = 'filament.pages.api-costs';

    public function getTitle(): string
    {
        return 'API costs (estimate)';
    }

    protected function getHeaderWidgets(): array
    {
        return [ApiUsageChart::class];
    }

    /** @return array<string, array<string, mixed>> */
    public function report(): array
    {
        return app(ApiCostReport::class)->cachedMonthToDate();
    }
}
