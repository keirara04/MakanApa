<?php

namespace App\Filament\Widgets;

use App\Models\AiJudgment;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Operations health: the queue worker is a hard dependency (halal triage, AI second opinions,
 * notifications), and the Judgment System's daily cost/failures should be visible at a glance.
 */
class SystemHealthWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'System health';

    protected function getStats(): array
    {
        $pending = DB::table('jobs')->count();
        $oldest = DB::table('jobs')->min('created_at');
        $failed = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();
        // A job sitting unreserved for >5 minutes almost always means no worker is running.
        $stale = $oldest !== null && Carbon::createFromTimestamp((int) $oldest)->lt(now()->subMinutes(5));

        $today = AiJudgment::where('created_at', '>=', today());
        $calls = (clone $today)->count();
        $tokens = (int) (clone $today)->sum(DB::raw('input_tokens + output_tokens'));
        $failures = (clone $today)->where('status', 'failed')->count();
        $rateLimited = (clone $today)->where('failure_reason', 'rate_limited')->count();
        $exhausted = (clone $today)->where('status', 'budget_exhausted')->distinct()->pluck('purpose')->all();

        return [
            Stat::make('Queue', $pending.' pending')
                ->description($stale
                    ? 'Oldest job waiting '.Carbon::createFromTimestamp((int) $oldest)->diffForHumans(null, true).' — is the queue worker running?'
                    : "{$failed} failed in 24h")
                ->color($stale || $failed > 0 ? 'danger' : 'success'),
            Stat::make('AI judgments today', $calls)
                ->description(number_format($tokens).' tokens · '.$failures.' failed · '.$rateLimited.' rate-limited'
                    .($exhausted ? ' · budget hit: '.implode(', ', $exhausted) : ''))
                ->color($failures > 0 || $exhausted ? 'warning' : 'gray'),
        ];
    }
}
