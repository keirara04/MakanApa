<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which client a session came from, so iOS and the web app can be compared. Existing rows
     * are all from the iOS app.
     */
    public function up(): void
    {
        Schema::table('app_sessions', function (Blueprint $table) {
            $table->string('platform', 16)->default('ios')->after('installation_id');
        });
    }

    public function down(): void
    {
        Schema::table('app_sessions', function (Blueprint $table) {
            $table->dropColumn('platform');
        });
    }
};
