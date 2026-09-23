<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_submissions', function (Blueprint $table) {
            // Judgment System triage summary (advisory only) and the inspectable priority math.
            $table->json('triage')->nullable();
            $table->json('review_priority_breakdown')->nullable();
        });

        Schema::table('restaurants', function (Blueprint $table) {
            // AI second opinion on "serves pork/alcohol?" — an admin review hint only; never
            // part of the halal ledger or the snapshot status.
            $table->json('halal_ai_hint')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_submissions', function (Blueprint $table) {
            $table->dropColumn(['triage', 'review_priority_breakdown']);
        });
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('halal_ai_hint');
        });
    }
};
