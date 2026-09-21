<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `radius_km` as `decimal(5,2)` (10m granularity) was too coarse for `isAreaCovered()`'s 1-meter
 * cache tolerance — any non-round radius (i.e. almost every real viewport-derived radius) got
 * rounded on write, then permanently failed its own "already covered" check on every later
 * identical request, silently re-hitting Google instead of reusing the cache. `->change()` needs
 * doctrine/dbal, which isn't installed here, so this uses a raw ALTER instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE place_sync_areas ALTER COLUMN radius_km TYPE numeric(8, 4)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE place_sync_areas ALTER COLUMN radius_km TYPE numeric(5, 2)');
    }
};
