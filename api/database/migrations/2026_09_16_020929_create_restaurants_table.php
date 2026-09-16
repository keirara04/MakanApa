<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('address')->nullable();
            $table->unsignedTinyInteger('price_level')->nullable();
            $table->decimal('rating', 2, 1)->nullable();
            $table->jsonb('opening_hours')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('provider')->nullable();
            $table->string('provider_place_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurants');
    }
};
