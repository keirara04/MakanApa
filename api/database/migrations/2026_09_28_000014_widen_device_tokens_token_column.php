<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // APNs tokens aren't fixed-length (simulator tokens are 160 hex chars) — 100 rejected them.
    public function up(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->string('token', 255)->change();
        });
    }

    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->string('token', 100)->change();
        });
    }
};
