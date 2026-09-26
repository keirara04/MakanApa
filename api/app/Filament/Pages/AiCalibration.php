<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AiJudgments\AiJudgmentResource;
use App\Models\AiJudgment;
use App\Services\Judgment\CalibrationReport;
use App\Services\Judgment\DefinitionRegistry;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Url;

/**
 * How well AI judgments matched later human decisions, per purpose + definition version.
 * Same numbers as `php artisan judgments:calibration` (both use CalibrationReport). Cached for a
 * few minutes per filter combination — the view asks on every Livewire update (each keystroke
 * in the threshold box), and the report reads every labelled judgment.
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
        return Cache::remember("admin-page:ai-calibration:versions:{$this->purpose}", now()->addMinutes(5), fn () => AiJudgment::where('purpose', $this->purpose)->distinct()->orderByDesc('definition_version')->pluck('definition_version')->all())
            ?: [DefinitionRegistry::latestVersion($this->purpose)];
    }

    public function report(): array
    {
        return Cache::remember(
            'admin-page:ai-calibration:report:'.md5(serialize([$this->purpose, $this->version, $this->since, $this->threshold])),
            now()->addMinutes(5),
            fn () => CalibrationReport::build($this->purpose, $this->version, $this->since ?: null, $this->threshold),
        );
    }

    public function totals(): array
    {
        return Cache::remember("admin-page:ai-calibration:totals:{$this->purpose}:{$this->version}", now()->addMinutes(5), function (): array {
            $base = AiJudgment::where('purpose', $this->purpose)
                ->when($this->version, fn ($q) => $q->where('definition_version', $this->version));

            return [
                'runs' => (clone $base)->count(),
                'ok' => (clone $base)->where('status', 'ok')->count(),
                'labelled' => (clone $base)->whereNotNull('outcome')->count(),
            ];
        });
    }

    public function updatedPurpose(): void
    {
        $this->version = null;
    }
}
