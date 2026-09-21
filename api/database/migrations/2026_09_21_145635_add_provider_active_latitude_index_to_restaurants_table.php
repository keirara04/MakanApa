<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Supports PlacesService's per-request "restaurants near a point" queries: `provider` and
     * `is_active` are equality filters, `latitude` is the leading range column the new
     * bounding-box `whereBetween` pre-filter scans. `longitude` deliberately left out —
     * confirmed with EXPLAIN ANALYZE that the range scan on `latitude` alone already prunes
     * enough that a residual filter on the remaining rows is cheap; widen this only if that
     * stops being true as the table grows.
     */
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->index(['provider', 'is_active', 'latitude']);
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropIndex(['provider', 'is_active', 'latitude']);
        });
    }
};
