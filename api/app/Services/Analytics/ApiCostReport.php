<?php

namespace App\Services\Analytics;

use App\Models\AiJudgment;
use App\Models\ApiUsageDaily;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

/**
 * Month-to-date outbound API spend, estimated from our own counters (api_usage_daily for Google
 * Places, token totals on ai_judgments for OpenRouter) priced with config/admin_budgets.php.
 * An estimate for staying inside budget, not a substitute for the providers' invoices.
 */
class ApiCostReport
{
    /**
     * Cached for the admin page; `admin:check-api-budget` calls monthToDate() directly so an
     * alert is never computed from a stale number.
     *
     * @return array<string, array<string, mixed>>
     */
    public function cachedMonthToDate(): array
    {
        return Cache::remember('admin:api-cost:mtd', now()->addMinutes(5), fn () => $this->monthToDate());
    }

    /**
     * Per provider: estimated cost so far this month, a straight-line projection to month end,
     * the budget, and one line per SKU/model.
     *
     * @return array<string, array{label: string, budget: float, cost: float, projected: float, percent: ?float, projectedPercent: ?float, lines: list<array{label: string, units: int, unitLabel: string, cost: float, projected: float}>}>
     */
    public function monthToDate(?CarbonInterface $now = null): array
    {
        $now ??= now();
        $start = $now->copy()->startOfMonth();
        // Today counts as a full day, so early-morning projections lean slightly low rather
        // than wildly high.
        $scale = $now->daysInMonth / max(1, $now->day);

        return [
            ApiUsageDaily::PROVIDER_GOOGLE_PLACES => $this->provider('google_places', $this->googleLines($start, $now, $scale)),
            'openrouter' => $this->provider('openrouter', $this->openRouterLines($start, $now, $scale)),
        ];
    }

    /**
     * @param  list<array{label: string, units: int, unitLabel: string, cost: float, projected: float}>  $lines
     * @return array{label: string, budget: float, cost: float, projected: float, percent: ?float, projectedPercent: ?float, lines: list<array{label: string, units: int, unitLabel: string, cost: float, projected: float}>}
     */
    private function provider(string $key, array $lines): array
    {
        $budget = (float) Config::get("admin_budgets.{$key}.monthly_budget_usd", 0);
        $cost = round(array_sum(array_column($lines, 'cost')), 2);
        $projected = round(array_sum(array_column($lines, 'projected')), 2);

        return [
            'label' => (string) Config::get("admin_budgets.{$key}.label", $key),
            'budget' => $budget,
            'cost' => $cost,
            'projected' => $projected,
            'percent' => $budget > 0 ? $cost / $budget : null,
            'projectedPercent' => $budget > 0 ? $projected / $budget : null,
            'lines' => $lines,
        ];
    }

    /** @return list<array{label: string, units: int, unitLabel: string, cost: float, projected: float}> */
    private function googleLines(CarbonInterface $start, CarbonInterface $now, float $scale): array
    {
        $calls = ApiUsageDaily::query()
            ->where('provider', ApiUsageDaily::PROVIDER_GOOGLE_PLACES)
            ->whereBetween('date', [$start->toDateString(), $now->toDateString()])
            ->selectRaw('endpoint, sum(calls) as total')
            ->groupBy('endpoint')
            ->pluck('total', 'endpoint')
            ->map(fn ($total) => (int) $total);

        $endpoints = (array) Config::get('admin_budgets.google_places.endpoints', []);

        // Configured SKUs first (stable order), then anything counted but not yet priced.
        return collect($endpoints)->keys()->merge($calls->keys())->unique()
            ->map(function (string $endpoint) use ($calls, $endpoints, $scale) {
                $sku = $endpoints[$endpoint] ?? ['label' => $endpoint.' (unpriced)', 'usd_per_1000' => 0, 'free_per_month' => 0];
                $units = $calls[$endpoint] ?? 0;
                $price = fn (float $n) => max(0, $n - (int) $sku['free_per_month']) * (float) $sku['usd_per_1000'] / 1000;

                return [
                    'label' => $sku['label'],
                    'units' => $units,
                    'unitLabel' => 'calls',
                    'cost' => round($price($units), 2),
                    'projected' => round($price($units * $scale), 2),
                ];
            })
            ->values()
            ->all();
    }

    /** @return list<array{label: string, units: int, unitLabel: string, cost: float, projected: float}> */
    private function openRouterLines(CarbonInterface $start, CarbonInterface $now, float $scale): array
    {
        $models = (array) Config::get('admin_budgets.openrouter.models', []);
        $default = (array) Config::get('admin_budgets.openrouter.default', []);

        return AiJudgment::query()
            ->whereBetween('created_at', [$start, $now])
            ->where('provider', 'openrouter')
            ->selectRaw('model, sum(input_tokens) as input_tokens, sum(output_tokens) as output_tokens')
            ->groupBy('model')
            ->get()
            ->map(function ($row) use ($models, $default, $scale) {
                $rates = str_ends_with((string) $row->model, ':free')
                    ? ['input_per_million' => 0, 'output_per_million' => 0]
                    : ($models[$row->model] ?? $default);
                $cost = ((int) $row->input_tokens * (float) ($rates['input_per_million'] ?? 0)
                    + (int) $row->output_tokens * (float) ($rates['output_per_million'] ?? 0)) / 1_000_000;

                return [
                    'label' => (string) $row->model,
                    'units' => (int) $row->input_tokens + (int) $row->output_tokens,
                    'unitLabel' => 'tokens',
                    'cost' => round($cost, 2),
                    'projected' => round($cost * $scale, 2),
                ];
            })
            ->values()
            ->all();
    }
}
