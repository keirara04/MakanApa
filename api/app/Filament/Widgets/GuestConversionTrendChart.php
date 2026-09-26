<?php

namespace App\Filament\Widgets;

use App\Services\Analytics\GuestConversionReport;
use Filament\Widgets\LineChartWidget;

/** Guests created vs guests upgraded to an account, per day. Shown on the Guest conversion page. */
class GuestConversionTrendChart extends LineChartWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    public ?string $filter = '30';

    public function getHeading(): string
    {
        return 'Guests created vs upgraded';
    }

    protected function getFilters(): ?array
    {
        return ['30' => 'Last 30 days', '90' => 'Last 90 days'];
    }

    protected function getData(): array
    {
        $daily = app(GuestConversionReport::class)->daily($this->filter === '90' ? 90 : 30);

        return [
            'datasets' => [
                ['label' => 'Guests created', 'data' => $daily['created'], 'borderColor' => '#94a3b8'],
                ['label' => 'Upgraded to account', 'data' => $daily['upgraded'], 'borderColor' => '#f59e0b'],
            ],
            'labels' => $daily['labels'],
        ];
    }
}
