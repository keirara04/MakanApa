<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Share funnel events (share_started/view/open_app/get_app) are per place. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_events', function (Blueprint $table) {
            $table->foreignId('restaurant_id')->nullable()->constrained()->nullOnDelete();
            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('marketing_events', function (Blueprint $table) {
            $table->dropIndex(['event', 'created_at']);
            $table->dropConstrainedForeignId('restaurant_id');
        });
    }
};
