<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Makan Brain's source of truth: every learning signal, one row per dimension it touches.
        // taste_profiles is only ever a materialized view of this (see TasteProfileBuilder), so a
        // profile can always be rebuilt and nothing learned is ever unauditable.
        Schema::create('taste_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('installation_id')->nullable();
            $table->string('session_id')->nullable();
            $table->foreignId('decision_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('decision_recommendation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('restaurant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('signal');      // accept | reroll | why_not | tune | vibe_tag | trait_feedback | trait_mute | reset
            $table->string('detail')->nullable();
            $table->string('dimension');   // category | cuisine | price | distance | vibe | novelty | trait | boundary
            $table->string('dimension_key')->default('');
            $table->float('value')->default(0);
            $table->string('scope')->default('both'); // long | pulse | both
            $table->string('source');      // explicit | behaviour | derived
            $table->string('authority');   // explicit | strong | medium | weak
            $table->string('slot')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Retries of the same feedback call (network timeout → iOS retries) must never teach twice.
            $table->unique(['decision_recommendation_id', 'signal', 'dimension', 'dimension_key'], 'taste_events_idempotency');
            $table->index(['user_id', 'id']);
            $table->index(['installation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taste_events');
    }
};
