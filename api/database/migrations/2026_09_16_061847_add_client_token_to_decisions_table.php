<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('decisions', function (Blueprint $table) {
            // Opaque per-decision secret returned only in the solo() response and required on
            // reroll/accept — the app has no login, so decision IDs are the only handle a client
            // has; without this, sequential IDs let anyone reroll/accept someone else's decision.
            $table->string('client_token', 64)->nullable()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('decisions', function (Blueprint $table) {
            $table->dropColumn('client_token');
        });
    }
};
