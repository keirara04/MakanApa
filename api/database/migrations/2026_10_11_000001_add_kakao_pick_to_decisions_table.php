<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A live-only Decide pick from Kakao Local (Korea). Kakao's terms allow storing only a place's
 * id and place_url, so this holds {place_id, url, shown_ids[], query, accepted_at} — never a
 * name, address or coordinates. Null for every normal decision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('decisions', function (Blueprint $table) {
            $table->json('kakao_pick')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('decisions', function (Blueprint $table) {
            $table->dropColumn('kakao_pick');
        });
    }
};
