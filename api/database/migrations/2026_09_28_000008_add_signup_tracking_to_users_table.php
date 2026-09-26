<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Which app surface a real account was created from ("login_screen", "nudge_picks",
            // "feature:post_in_the_community" …) — what the guest-conversion funnel is read from.
            $table->string('signup_source', 40)->nullable();
            // Set when a guest row turns into a real account (rather than a fresh sign-up).
            $table->timestamp('upgraded_from_guest_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['upgraded_from_guest_at']);
            $table->dropColumn(['signup_source', 'upgraded_from_guest_at']);
        });
    }
};
