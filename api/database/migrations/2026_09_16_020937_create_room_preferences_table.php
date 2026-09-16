<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_member_id')->constrained()->cascadeOnDelete();
            $table->string('preference_type');
            $table->string('value');
            $table->integer('weight')->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_preferences');
    }
};
