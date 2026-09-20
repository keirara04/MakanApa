<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_affiliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('type');
            // Restrict, not nullOnDelete — universities are historical reference data; retire one
            // via `active = false` rather than deleting it out from under existing affiliations.
            $table->foreignId('university_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('verification_status')->default('verified');
            $table->string('verification_method')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_affiliations');
    }
};
