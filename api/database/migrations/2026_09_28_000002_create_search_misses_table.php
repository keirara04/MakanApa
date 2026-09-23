<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Searches that found nothing — aggregate and anonymous by construction: the normalized query
 * and a ~1 km area cell, a hit counter, nothing about who searched. Tells admins which places
 * MakanApa is missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_misses', function (Blueprint $table) {
            $table->id();
            $table->string('query', 100);
            $table->decimal('latitude_cell', 6, 2);
            $table->decimal('longitude_cell', 6, 2);
            $table->unsignedInteger('hits')->default(1);
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['query', 'latitude_cell', 'longitude_cell']);
            $table->index(['hits', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_misses');
    }
};
