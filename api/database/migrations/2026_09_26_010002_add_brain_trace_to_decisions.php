<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Decision fingerprint — enough to answer "why did decision N choose this place?" exactly,
        // months later, and to cohort v1 vs Makan Brain in the evaluation page.
        Schema::table('decisions', function (Blueprint $table) {
            $table->string('session_id')->nullable()->after('installation_id');
            $table->string('algorithm_version')->default('v1');
            $table->unsignedInteger('taste_profile_version')->nullable();
            $table->string('selera_stage')->nullable();
            $table->string('intent_type')->nullable(); // craving | mood | anything
            $table->string('lens')->nullable();
            $table->json('tunes')->nullable();
            $table->json('context_snapshot')->nullable();
            $table->json('weight_snapshot')->nullable();
            $table->unsignedSmallInteger('candidate_count')->nullable();
            $table->json('funnel')->nullable();
            $table->float('exploration_level')->nullable();
            $table->boolean('fatigue_mode')->default(false);
            $table->unsignedSmallInteger('reason_catalog_version')->nullable();

            $table->index(['user_id', 'id']);
            $table->index(['installation_id', 'id']);
            $table->index(['algorithm_version', 'created_at']);
        });

        Schema::table('decision_recommendations', function (Blueprint $table) {
            $table->json('breakdown')->nullable();
            $table->unsignedSmallInteger('score_rank')->nullable();
            $table->float('selection_probability')->nullable();
            $table->boolean('selected')->default(false);
            $table->json('reason_facts')->nullable();
            $table->string('reject_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('decision_recommendations', function (Blueprint $table) {
            $table->dropColumn(['breakdown', 'score_rank', 'selection_probability', 'selected', 'reason_facts', 'reject_reason']);
        });

        Schema::table('decisions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'id']);
            $table->dropIndex(['installation_id', 'id']);
            $table->dropIndex(['algorithm_version', 'created_at']);
            $table->dropColumn([
                'session_id', 'algorithm_version', 'taste_profile_version', 'selera_stage', 'intent_type', 'lens', 'tunes',
                'context_snapshot', 'weight_snapshot', 'candidate_count', 'funnel', 'exploration_level', 'fatigue_mode',
                'reason_catalog_version',
            ]);
        });
    }
};
