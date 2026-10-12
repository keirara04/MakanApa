<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a pick with a mood or typed craving found anything that fits (the "Nothing Indian
 * nearby fits right now" note). Null when there was no mood or craving. Feeds the admin's
 * per-campus match rate: a low rate means that area's place data is thin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('decisions', function (Blueprint $table) {
            $table->boolean('intent_matched')->nullable()->after('intent_type');
        });
    }

    public function down(): void
    {
        Schema::table('decisions', function (Blueprint $table) {
            $table->dropColumn('intent_matched');
        });
    }
};
