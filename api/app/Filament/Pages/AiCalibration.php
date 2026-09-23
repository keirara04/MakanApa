<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AiJudgments\AiJudgmentResource;
use App\Models\AiJudgment;
use App\Services\Judgment\CalibrationReport;
use App\Services\Judgment\DefinitionRegistry;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

/**
 * How well AI judgments matched later human decisions, per purpose + definition version.
 * Same numbers as `php artisan judgments:calibration` (both use CalibrationReport).
 */
class AiCalibration extends Page
{
    protected static \UnitEnum|string|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'AI Calibration';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $slug = 'ai/calibration';

    protected string $view = 'filament.pages.ai-calibration';

    #[Url]
    public string $purpose = 'halal_triage';

    #[Url]
    public ?int $version = null;

    #[Url]
    public float $threshold = 0.5;

    #[Url]
    public ?string $since = null;

    public function getTitle(): string
    {
        return 'AI Calibration';
    }

    public function purposes(): array
    {
        return collect(AiJudgmentResource::purposeOptions())
            ->only(['halal_triage', 'non_halal_second_opinion'])
            ->all();
    }

    public function versions(): array
    {
        return AiJudgment::where('purpose', $this->purpose)->distinct()->orderByDesc('definition_version')->pluck('definition_version')->all()
            ?: [DefinitionRegistry::latestVersion($this->purpose)];
    }

    public function report(): array
    {
        return CalibrationReport::build($this->purpose, $this->version, $this->since ?: null, $this->threshold);
    }

    public function totals(): array
    {
        $base = AiJudgment::where('purpose', $this->purpose)
            ->when($this->version, fn ($q) => $q->where('definition_version', $this->version));

        return [
            'runs' => (clone $base)->count(),
            'ok' => (clone $base)->where('status', 'ok')->count(),
            'labelled' => (clone $base)->whereNotNull('outcome')->count(),
        ];
    }

    public function updatedPurpose(): void
    {
        $this->version = null;
    }
}
