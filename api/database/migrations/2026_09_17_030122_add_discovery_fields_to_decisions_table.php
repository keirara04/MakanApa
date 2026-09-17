<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('decisions', function (Blueprint $table) {
            $table->string('discovery_mode')->nullable();
            $table->string('vibe')->nullable();
            // Anonymous, client-generated device id (no login exists app-wide) — lets
            // personalFitComponent look up this installation's own accept history.
            $table->string('installation_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('decisions', function (Blueprint $table) {
            $table->dropColumn(['discovery_mode', 'vibe', 'installation_id']);
        });
    }
};
