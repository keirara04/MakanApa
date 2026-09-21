<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->string('name')->nullable()->change();
            $table->string('apple_sub')->nullable()->unique()->after('password');
            $table->string('google_sub')->nullable()->unique()->after('apple_sub');
            // Encrypted (Laravel `encrypted` cast on the model) so account deletion can call
            // Apple's revocation endpoint later — see pending_provider_links migration note.
            $table->text('apple_refresh_token')->nullable()->after('google_sub');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['apple_sub', 'google_sub', 'apple_refresh_token']);
            $table->string('password')->nullable(false)->change();
            $table->string('name')->nullable(false)->change();
        });
    }
};
