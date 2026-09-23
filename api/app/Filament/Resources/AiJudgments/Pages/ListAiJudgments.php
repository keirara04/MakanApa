<?php

namespace App\Filament\Resources\AiJudgments\Pages;

use App\Filament\Pages\AiCalibration;
use App\Filament\Resources\AiJudgments\AiJudgmentResource;
use App\Filament\Widgets\AiJudgmentUsageChart;
use App\Filament\Widgets\SystemHealthWidget;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListAiJudgments extends ListRecords
{
    protected static string $resource = AiJudgmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('calibration')->label('Calibration report')->icon('heroicon-o-chart-bar')
                ->url(AiCalibration::getUrl()),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [SystemHealthWidget::class, AiJudgmentUsageChart::class];
    }
}
