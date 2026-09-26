<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One counter row per day × provider × billable endpoint (SKU) — the admin API cost page
        // prices these against config/admin_budgets.php. Aggregate only, never per user/request.
        Schema::create('api_usage_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('provider', 40);
            $table->string('endpoint', 60);
            $table->unsignedBigInteger('calls')->default(0);
            $table->timestamps();
            $table->unique(['date', 'provider', 'endpoint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_usage_daily');
    }
};
