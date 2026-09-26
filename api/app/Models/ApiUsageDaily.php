<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Daily count of billable outbound API calls, per provider and endpoint (SKU). What the admin
 * API cost page and `admin:check-api-budget` price against config/admin_budgets.php.
 */
#[Fillable(['date', 'provider', 'endpoint', 'calls'])]
class ApiUsageDaily extends Model
{
    public const PROVIDER_GOOGLE_PLACES = 'google_places';

    protected $table = 'api_usage_daily';

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'calls' => 'integer',
        ];
    }

    /**
     * One atomic upsert (INSERT … ON CONFLICT DO UPDATE calls = calls + n). Accounting must never
     * cost a user their request, so any failure is reported and swallowed — and it runs in its
     * own savepoint, so a failed write can't poison a surrounding Postgres transaction.
     */
    public static function record(string $provider, string $endpoint, int $calls = 1): void
    {
        if ($calls < 1) {
            return;
        }

        try {
            DB::transaction(function () use ($provider, $endpoint, $calls) {
                $now = now();

                static::query()->upsert(
                    [[
                        'date' => $now->toDateString(),
                        'provider' => $provider,
                        'endpoint' => $endpoint,
                        'calls' => $calls,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]],
                    ['date', 'provider', 'endpoint'],
                    ['calls' => DB::raw('api_usage_daily.calls + '.(int) $calls), 'updated_at' => $now],
                );
            });
        } catch (Throwable $e) {
            report($e);
        }
    }
}
