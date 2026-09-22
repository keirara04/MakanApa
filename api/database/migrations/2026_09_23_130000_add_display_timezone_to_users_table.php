<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Admin-only display preference — storage/PHP date functions stay on config('app.timezone')
            // (UTC), this only changes how Filament renders timestamps to this admin. Null means
            // "no preference set yet", not "no timezone" — see User::displayTimezone().
            $table->string('display_timezone')->nullable()->after('notification_preferences');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('display_timezone');
        });
    }
};
