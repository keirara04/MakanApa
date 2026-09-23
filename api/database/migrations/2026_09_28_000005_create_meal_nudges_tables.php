<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mealtime nudges. `meal_nudge_states` holds everything dispatch needs as O(1) per-user state
 * (next send time, back-off counters) so it never scans history; `meal_nudges` is the log and
 * funnel (sent → opened → place opened → acted), unique per user per local day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_nudge_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamp('next_nudge_at')->nullable()->index();
            $table->string('next_slot')->nullable();
            $table->unsignedSmallInteger('unopened_streak')->default(0);
            $table->unsignedSmallInteger('pause_count')->default(0);
            $table->timestamp('paused_until')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->timestamps();
        });

        Schema::create('meal_nudges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('local_date');
            $table->string('slot');
            $table->foreignId('restaurant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('copy_key');
            $table->string('status');
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('place_opened_at')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->string('action')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'local_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_nudges');
        Schema::dropIfExists('meal_nudge_states');
    }
};
