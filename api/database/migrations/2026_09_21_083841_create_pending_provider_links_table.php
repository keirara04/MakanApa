<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_provider_links', function (Blueprint $table) {
            $table->id();
            // SHA-256 of a high-entropy random token, same treatment as a password-reset token —
            // the raw token is returned to the client once and never persisted, so a DB leak of
            // unconsumed rows can't be used directly to complete a link.
            $table->string('token_hash')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            // Already verified server-side at issuance time (see AuthController::apple/google) —
            // the client never supplies this value.
            $table->string('provider_sub');
            // Only ever set for provider = apple. The authorizationCode Apple hands back is
            // single-use, so it's exchanged for a refresh token at first-contact time (during
            // /auth/apple) even when the account turns out to need linking rather than an
            // immediate login — otherwise that refresh token would be lost by the time the user
            // completes the link with their password. Copied onto the real user row when the
            // link is consumed (see AuthController::link()), never exposed in any response.
            $table->text('apple_refresh_token')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_provider_links');
    }
};
