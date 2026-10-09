<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restaurants an ambassador hand-picks for their community, with an optional short note. The
 * community is snapshotted at pick time; the feed only shows picks whose picker is *still* the
 * ambassador of that community, so reassigning an ambassador hides their old picks without cleanup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ambassador_picks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('university_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 140)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'restaurant_id']);
            $table->index(['university_id', 'created_at']);
            $table->index(['area_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ambassador_picks');
    }
};
