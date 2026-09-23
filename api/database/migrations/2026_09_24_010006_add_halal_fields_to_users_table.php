<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('halal_preference')->default(false);
            // Internal moderation signals only — never exposed in any API payload.
            $table->boolean('trusted_contributor')->default(false);
            $table->json('contribution_stats')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['halal_preference', 'trusted_contributor', 'contribution_stats']);
        });
    }
};
