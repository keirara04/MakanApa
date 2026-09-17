<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_saves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('installation_id');
            $table->timestamp('created_at')->useCurrent();

            // Makes save/unsave idempotent by construction — no counter to double-increment.
            $table->unique(['restaurant_id', 'installation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_saves');
    }
};
