<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per certificate a restaurant has ever held — renewals add a row rather than
        // overwriting, so "what did the 2025 cert say" stays answerable.
        Schema::create('restaurant_halal_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('authority'); // CertificationAuthority
            $table->string('certificate_number');
            $table->string('holder_name')->nullable();
            $table->string('premise_name')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at');
            $table->string('verification_method'); // CertificateVerificationMethod
            $table->string('registry_url')->nullable();
            $table->timestamp('registry_checked_at')->nullable();
            $table->string('status')->default('valid'); // CertificateStatus
            $table->foreignId('certificate_photo_id')->nullable()->constrained('restaurant_photos')->nullOnDelete();
            $table->timestamps();

            $table->unique(['restaurant_id', 'authority', 'certificate_number']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_halal_certificates');
    }
};
