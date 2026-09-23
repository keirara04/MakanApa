<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Denormalized current state for fast reads (filters, map, ranking). Written only by
        // HalalSnapshotService — never set these columns directly.
        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('halal_status')->default('unknown')->index();
            $table->string('halal_review_state')->default('clear')->index();
            $table->foreignId('halal_active_verification_id')->nullable()->constrained('restaurant_halal_verifications')->nullOnDelete();
            $table->foreignId('halal_active_certificate_id')->nullable()->constrained('restaurant_halal_certificates')->nullOnDelete();
            $table->timestamp('halal_verified_at')->nullable();
            $table->date('halal_expires_at')->nullable();
            $table->unsignedInteger('halal_open_report_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('halal_active_verification_id');
            $table->dropConstrainedForeignId('halal_active_certificate_id');
            $table->dropColumn(['halal_status', 'halal_review_state', 'halal_verified_at', 'halal_expires_at', 'halal_open_report_count']);
        });
    }
};
