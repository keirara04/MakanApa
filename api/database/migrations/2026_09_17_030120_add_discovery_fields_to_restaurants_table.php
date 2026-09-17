<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->unsignedInteger('user_rating_count')->nullable();

            // Rebuildable cache of decision_recommendations — decision_recommendations remains
            // the source of truth (see php artisan restaurants:rebuild-signal-counts).
            $table->unsignedInteger('impressions_count')->default(0);
            $table->unsignedInteger('accepted_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['user_rating_count', 'impressions_count', 'accepted_count', 'rejected_count']);
        });
    }
};
