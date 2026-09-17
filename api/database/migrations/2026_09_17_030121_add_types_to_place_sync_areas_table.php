<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('place_sync_areas', function (Blueprint $table) {
            // Which Google includedTypes were fetched for this synced circle — isAreaCovered()
            // requires a superset match, not just a location match, so a Normal-mode sync can't
            // wrongly look "covered" to a later Cafe-mode request needing cafe/coffee_shop/bakery.
            $table->jsonb('types')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('place_sync_areas', function (Blueprint $table) {
            $table->dropColumn('types');
        });
    }
};
