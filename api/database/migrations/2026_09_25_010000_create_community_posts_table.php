<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // One level of replies only — a reply's parent is always a top-level post (enforced
            // in CommunityPostService, not here). A reply inherits its parent's community.
            $table->foreignId('parent_id')->nullable()->constrained('community_posts')->cascadeOnDelete();
            // Exactly one of university_id/area_id — see the CHECK constraint below. Public
            // (unaffiliated) users can read nothing and post nothing: there's no stable scope.
            $table->foreignId('university_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->restrictOnDelete();
            // Optional place tag, top-level posts only. nullOnDelete: a merged/removed restaurant
            // shouldn't take the conversation down with it.
            $table->foreignId('restaurant_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->string('status')->default('visible'); // visible | hidden | removed
            $table->string('hidden_reason')->nullable(); // reports | admin
            // Denormalized counters, always recomputed from the source tables (never ++/--),
            // so they can't drift under concurrent writes.
            $table->unsignedInteger('reaction_count')->default(0);
            $table->unsignedInteger('reply_count')->default(0);
            $table->unsignedInteger('report_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['university_id', 'parent_id', 'id']);
            $table->index(['area_id', 'parent_id', 'id']);
            $table->index(['parent_id', 'id']);
            $table->index(['restaurant_id', 'id']);
            $table->index(['status', 'report_count']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE community_posts ADD CONSTRAINT community_posts_exactly_one_community CHECK ((university_id IS NULL) <> (area_id IS NULL))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('community_posts');
    }
};
