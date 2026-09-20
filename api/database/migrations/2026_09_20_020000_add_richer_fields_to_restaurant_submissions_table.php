<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_submissions', function (Blueprint $table) {
            // Which of the snapshot fields the submitter actually intends to change — the
            // snapshot itself stays full (moderation context/audit), only these get materialized
            // as overrides/updates at approval time. See RestaurantField::OVERRIDABLE plus the
            // literal "menu_items".
            $table->json('changed_fields')->nullable()->after('notes');
            $table->string('phone')->nullable()->after('price_level');
            $table->string('instagram_handle')->nullable()->after('phone');
            $table->string('tiktok_handle')->nullable()->after('instagram_handle');
            $table->string('website_url')->nullable()->after('tiktok_handle');
            $table->json('menu_items')->nullable()->after('website_url');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_submissions', function (Blueprint $table) {
            $table->dropColumn(['changed_fields', 'phone', 'instagram_handle', 'tiktok_handle', 'website_url', 'menu_items']);
        });
    }
};
