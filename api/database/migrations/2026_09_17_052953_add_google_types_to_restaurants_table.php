<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            // Raw Google Places `types[]` for this row — lets readGoogleRestaurantsNear() filter
            // by DiscoveryMode without re-deriving type from food_category/tags (which are lossy,
            // curated mappings, not a faithful copy of what Google actually returned). Null on
            // rows synced before this column existed — treated as compatible with any mode.
            $table->jsonb('google_types')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('google_types');
        });
    }
};
