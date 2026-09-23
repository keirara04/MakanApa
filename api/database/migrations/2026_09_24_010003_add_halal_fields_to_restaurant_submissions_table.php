<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_submissions', function (Blueprint $table) {
            // Reporter's side. halal_comment is PUBLIC once approved — notes stays admin-only.
            $table->string('halal_claim')->nullable();
            $table->text('halal_comment')->nullable();
            $table->string('certification_authority')->nullable();
            $table->string('certificate_number')->nullable();
            $table->date('certificate_issued_at')->nullable();
            $table->date('certificate_expires_at')->nullable();
            // Moderator's side — the claim above is never mutated, so both facts survive.
            $table->string('halal_resolved_status')->nullable();
            $table->integer('review_priority')->default(0);
            $table->string('contact_phone')->nullable(); // owner_claim only, admin-only

            $table->index(['submission_type', 'status', 'review_priority']);
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_submissions', function (Blueprint $table) {
            $table->dropIndex(['submission_type', 'status', 'review_priority']);
            $table->dropColumn([
                'halal_claim', 'halal_comment', 'certification_authority', 'certificate_number',
                'certificate_issued_at', 'certificate_expires_at', 'halal_resolved_status',
                'review_priority', 'contact_phone',
            ]);
        });
    }
};
