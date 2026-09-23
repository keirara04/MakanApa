<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_post_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // CommunityReaction value
            $table->timestamps();

            // One reaction per user per post — switching type updates the row in place.
            $table->unique(['community_post_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_post_reactions');
    }
};
