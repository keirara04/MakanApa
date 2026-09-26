<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per agreement (never updated), so there's a history of which versions a user
        // agreed to and when — the evidence Apple guideline 1.2's "users agree to terms" and a
        // clickwrap dispute both need. The current versions live in config/legal.php.
        Schema::create('terms_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('terms_version', 20);
            $table->string('guidelines_version', 20);
            $table->string('privacy_version', 20);
            $table->string('context', 20);
            $table->string('app_version', 40)->nullable();
            $table->timestamp('accepted_at');
            $table->timestamps();

            $table->index(['user_id', 'accepted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terms_acceptances');
    }
};
