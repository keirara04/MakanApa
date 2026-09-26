<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

#[Fillable(['query', 'latitude_cell', 'longitude_cell', 'hits', 'last_seen_at', 'resolved_at', 'resolved_by'])]
class SearchMiss extends Model
{
    protected function casts(): array
    {
        return [
            'latitude_cell' => 'decimal:2',
            'longitude_cell' => 'decimal:2',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Still needs attention: never resolved, or searched (and missed) again since it was — the
     * earlier fix evidently didn't make the place findable.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('resolved_at')->orWhereColumn('last_seen_at', '>', 'resolved_at'));
    }

    public function isOpen(): bool
    {
        return $this->resolved_at === null || $this->last_seen_at?->gt($this->resolved_at);
    }

    /** One atomic upsert per empty search — the counter goes up, no per-user row is ever kept. */
    public static function record(string $normalizedQuery, float $latitude, float $longitude): void
    {
        $now = now();

        static::query()->upsert(
            [[
                'query' => mb_substr($normalizedQuery, 0, 100),
                'latitude_cell' => round($latitude, 2),
                'longitude_cell' => round($longitude, 2),
                'hits' => 1,
                'last_seen_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['query', 'latitude_cell', 'longitude_cell'],
            ['hits' => DB::raw('search_misses.hits + 1'), 'last_seen_at' => $now, 'updated_at' => $now],
        );
    }
}
