<?php

namespace App\Console\Commands;

use App\Services\Judgment\CalibrationReport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * How well did logged judgments match what humans later decided? Always scoped to ONE
 * definition version — changed questions are a different experiment. Same numbers as the
 * Filament "AI Calibration" page (both use CalibrationReport).
 */
#[Signature('judgments:calibration {purpose} {--definition-version= : Definition version (default: latest)} {--since= : Only judgments created on/after this date} {--threshold=0.5 : Decision threshold for FP/FN}')]
#[Description('Calibration report for a judgment purpose: Brier score, probability bands, FP/FN')]
class JudgmentCalibration extends Command
{
    public function handle(): int
    {
        $report = CalibrationReport::build(
            $this->argument('purpose'),
            $this->option('definition-version') ? (int) $this->option('definition-version') : null,
            $this->option('since'),
            (float) $this->option('threshold'),
        );

        $this->info("{$report['purpose']} v{$report['version']}: {$report['labelled']} labelled judgments");

        foreach ($report['questions'] as $questionId => $q) {
            $this->newLine();
            if ($q['kind'] === 'choice') {
                $this->line(sprintf('<comment>%s</comment>  n=%d  top-1 hit rate=%.2f', $questionId, $q['n'], $q['hitRate']));

                continue;
            }
            $this->line(sprintf('<comment>%s</comment>  n=%d  Brier=%.4f  base rate=%.2f  FP=%d  FN=%d  (threshold %.2f)',
                $questionId, $q['n'], $q['brier'], $q['baseRate'], $q['falsePositives'], $q['falseNegatives'], $report['threshold']));
            $this->table(['predicted', 'n', 'actual yes rate', 'mean predicted'], array_map(fn ($b) => [
                $b['range'], $b['n'], sprintf('%.2f', $b['actualRate']), sprintf('%.2f', $b['meanPredicted']),
            ], $q['bands']));
        }

        return self::SUCCESS;
    }
}
