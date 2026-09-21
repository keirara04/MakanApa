<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            // Self-referencing: set when this restaurant is merged away into a canonical
            // duplicate by RestaurantMergeService. Never null-and-reused — a merged row stays
            // merged forever (see canonicalRestaurant()/RestaurantMergeService's chained-merge guard).
            $table->foreignId('merged_into_restaurant_id')->nullable()->constrained('restaurants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merged_into_restaurant_id');
        });
    }
};
