<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Filament panel MFA (email code) — optional, per-admin opt-in via the panel's
            // profile page. Irrelevant to the iOS app's own auth, only read by the admin panel.
            $table->boolean('has_email_authentication')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('has_email_authentication');
        });
    }
};
