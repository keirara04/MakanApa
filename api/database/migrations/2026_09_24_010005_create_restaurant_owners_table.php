<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Verified owner link. Ownership never bypasses moderation — it only tags evidence as
        // restaurant_owner and raises its review priority.
        Schema::create('restaurant_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending | verified | revoked
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('claim_submission_id')->nullable()->constrained('restaurant_submissions')->nullOnDelete();
            $table->timestamps();

            $table->unique(['restaurant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_owners');
    }
};
