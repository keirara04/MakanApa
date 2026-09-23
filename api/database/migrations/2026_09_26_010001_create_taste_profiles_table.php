<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taste_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->string('installation_id')->nullable()->unique();
            $table->json('memory');
            $table->json('muted');
            $table->json('corrections');
            $table->unsignedInteger('signal_count')->default(0);
            $table->unsignedInteger('version')->default(0);
            $table->unsignedBigInteger('last_event_id')->nullable();
            $table->unsignedBigInteger('reset_at_event_id')->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE taste_profiles ADD CONSTRAINT taste_profiles_has_owner CHECK (user_id IS NOT NULL OR installation_id IS NOT NULL)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('taste_profiles');
    }
};
