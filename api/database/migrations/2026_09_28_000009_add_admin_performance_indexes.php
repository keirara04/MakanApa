<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for what the Filament admin panel actually filters and sorts on — sidebar badges,
 * default table sorts, dashboard chart date ranges and the halal work-queue tabs were all
 * sequential scans. Postgres does not index foreign keys on its own, hence the FK ones too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_submissions', function (Blueprint $table) {
            // "Pending" badge + NeedsAttention count, and the submissions table's default sort.
            $table->index(['status', 'created_at']);
        });

        Schema::table('community_requests', function (Blueprint $table) {
            $table->index('status');
        });

        // The reported-posts badge and the posts table's default "Has open reports" filter — a
        // tiny partial index, since almost every post has zero reports.
        DB::statement('CREATE INDEX community_posts_reported_id_index ON community_posts (id) WHERE report_count > 0');

        Schema::table('restaurant_photos', function (Blueprint $table) {
            // Evidence photos per submission (submission page, duplicate-photo check).
            $table->index('restaurant_submission_id');
        });

        Schema::table('notification_deliveries', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('decision_recommendations', function (Blueprint $table) {
            // Recommendation accept/reroll chart (last 14 days).
            $table->index('shown_at');
        });

        Schema::table('app_sessions', function (Blueprint $table) {
            // App opens chart — the existing (user_id, started_at) index can't serve a pure time range.
            $table->index('started_at');
        });

        Schema::table('users', function (Blueprint $table) {
            // New-users chart and the guest-conversion cohort.
            $table->index('created_at');
        });

        Schema::table('restaurants', function (Blueprint $table) {
            // Halal "Needs verification" tab: unknown status, most-shown first.
            $table->index(['halal_status', 'impressions_count']);
            // Halal "Recently changed" tab.
            $table->index('halal_verified_at');
            // Submission duplicate check's bounding box (no provider filter, unlike the Nearby index).
            $table->index(['latitude', 'longitude']);
        });

        // Halal "AI flagged" tab filters and sorts on this exact expression.
        DB::statement("CREATE INDEX restaurants_halal_ai_probability_index ON restaurants (((halal_ai_hint->>'probability')::float))");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS restaurants_halal_ai_probability_index');

        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropIndex(['latitude', 'longitude']);
            $table->dropIndex(['halal_verified_at']);
            $table->dropIndex(['halal_status', 'impressions_count']);
        });

        Schema::table('users', fn (Blueprint $table) => $table->dropIndex(['created_at']));
        Schema::table('app_sessions', fn (Blueprint $table) => $table->dropIndex(['started_at']));
        Schema::table('decision_recommendations', fn (Blueprint $table) => $table->dropIndex(['shown_at']));
        Schema::table('admin_audit_logs', fn (Blueprint $table) => $table->dropIndex(['created_at']));
        Schema::table('notification_deliveries', fn (Blueprint $table) => $table->dropIndex(['created_at']));
        Schema::table('restaurant_photos', fn (Blueprint $table) => $table->dropIndex(['restaurant_submission_id']));

        DB::statement('DROP INDEX IF EXISTS community_posts_reported_id_index');

        Schema::table('community_requests', fn (Blueprint $table) => $table->dropIndex(['status']));
        Schema::table('restaurant_submissions', fn (Blueprint $table) => $table->dropIndex(['status', 'created_at']));
    }
};
