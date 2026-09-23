<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the hot read paths. Postgres never indexes a foreign key on its own, so every
 * `foreignId()->constrained()` column below was a sequential scan until now.
 *
 * `(provider, provider_place_id)` is deliberately NOT unique yet: concurrent syncs could already
 * have written duplicates, and a unique index would fail the deploy on those. Dedupe first, then
 * promote it in a follow-up migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            // PlacesService::upsertRestaurant() / resolveGooglePlace() lookups.
            $table->index(['provider', 'provider_place_id']);
        });

        Schema::table('place_sync_areas', function (Blueprint $table) {
            // PlacesService::isAreaCovered() — fresh rows for one provider.
            $table->index(['provider', 'synced_at']);
        });

        Schema::table('decision_recommendations', function (Blueprint $table) {
            // reroll / accept / vibe-tag / tune all read one decision's rows.
            $table->index(['decision_id', 'score_rank']);
            // Community feed + pick stats aggregate accepted rows over a time window.
            $table->index(['accepted_at', 'restaurant_id']);
        });

        Schema::table('decisions', function (Blueprint $table) {
            $table->index(['university_id', 'created_at']);
            $table->index(['area_id', 'created_at']);
        });

        Schema::table('taste_events', function (Blueprint $table) {
            // MakanBrain::sessionActions() on every reroll.
            $table->index(['session_id', 'signal']);
        });

        Schema::table('restaurant_vibe_votes', function (Blueprint $table) {
            $table->index('restaurant_id');
        });

        Schema::table('restaurant_menu_items', function (Blueprint $table) {
            $table->index(['restaurant_id', 'sort_order']);
        });

        Schema::table('restaurant_photos', function (Blueprint $table) {
            $table->index(['restaurant_id', 'is_active']);
        });

        Schema::table('restaurant_tags', function (Blueprint $table) {
            // The pivot's primary key leads with restaurant_id; this serves the reverse direction.
            $table->index('tag_id');
        });

        Schema::table('restaurant_cuisine', function (Blueprint $table) {
            $table->index('cuisine_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            // Trigram indexes make PlacesService::searchLocalRestaurants()'s `ILIKE '%q%'`
            // index-assisted instead of a full scan. pg_trgm is a trusted extension (PG13+), so
            // the database owner can create it without superuser.
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
            DB::statement('CREATE INDEX IF NOT EXISTS restaurants_name_trgm_index ON restaurants USING gin (name gin_trgm_ops)');
            DB::statement('CREATE INDEX IF NOT EXISTS restaurants_signature_dish_trgm_index ON restaurants USING gin (signature_dish gin_trgm_ops)');
            DB::statement('CREATE INDEX IF NOT EXISTS restaurant_menu_items_name_trgm_index ON restaurant_menu_items USING gin (name gin_trgm_ops)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS restaurant_menu_items_name_trgm_index');
            DB::statement('DROP INDEX IF EXISTS restaurants_signature_dish_trgm_index');
            DB::statement('DROP INDEX IF EXISTS restaurants_name_trgm_index');
            // The pg_trgm extension itself is left installed — other objects may depend on it.
        }

        Schema::table('restaurant_cuisine', fn (Blueprint $table) => $table->dropIndex(['cuisine_id']));
        Schema::table('restaurant_tags', fn (Blueprint $table) => $table->dropIndex(['tag_id']));
        Schema::table('restaurant_photos', fn (Blueprint $table) => $table->dropIndex(['restaurant_id', 'is_active']));
        Schema::table('restaurant_menu_items', fn (Blueprint $table) => $table->dropIndex(['restaurant_id', 'sort_order']));
        Schema::table('restaurant_vibe_votes', fn (Blueprint $table) => $table->dropIndex(['restaurant_id']));
        Schema::table('taste_events', fn (Blueprint $table) => $table->dropIndex(['session_id', 'signal']));
        Schema::table('decisions', function (Blueprint $table) {
            $table->dropIndex(['area_id', 'created_at']);
            $table->dropIndex(['university_id', 'created_at']);
        });
        Schema::table('decision_recommendations', function (Blueprint $table) {
            $table->dropIndex(['accepted_at', 'restaurant_id']);
            $table->dropIndex(['decision_id', 'score_rank']);
        });
        Schema::table('place_sync_areas', fn (Blueprint $table) => $table->dropIndex(['provider', 'synced_at']));
        Schema::table('restaurants', fn (Blueprint $table) => $table->dropIndex(['provider', 'provider_place_id']));
    }
};
