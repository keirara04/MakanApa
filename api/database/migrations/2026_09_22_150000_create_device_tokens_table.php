<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            // Nullable: an installation can register anonymously (pre-login) and only gets a
            // user_id once claim() runs. Registration must never clear this once set — see
            // DeviceTokenUpsertService.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Durable device identity (pairs with iOS InstallationID.current) — the APNs token
            // itself rotates over time and is never treated as the identity.
            $table->string('installation_id');
            $table->string('token', 100);
            $table->string('platform')->default('ios');
            $table->string('environment'); // sandbox | production
            $table->timestamp('last_seen_at')->nullable();
            // Set when APNs reports the token as dead — kept for diagnostics/history rather
            // than deleting the row outright.
            $table->timestamp('invalidated_at')->nullable();
            $table->timestamps();

            $table->unique(['installation_id', 'environment']);
            $table->unique(['token', 'environment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
