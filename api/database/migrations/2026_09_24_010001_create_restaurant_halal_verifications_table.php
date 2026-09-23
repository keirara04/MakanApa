<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only decision ledger — the source of truth for halal status. Rows are immutable
        // except for state/effective_until/superseded_by_id; restaurants.halal_* is only a
        // denormalized snapshot rebuilt from here (HalalSnapshotService).
        Schema::create('restaurant_halal_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submission_id')->nullable()->constrained('restaurant_submissions')->nullOnDelete();
            $table->foreignId('certificate_id')->nullable()->constrained('restaurant_halal_certificates')->nullOnDelete();
            $table->foreignId('moderator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status'); // HalalStatus
            $table->string('evidence_source'); // HalalEvidenceSource
            $table->string('decision_method'); // HalalDecisionMethod
            $table->string('state')->default('active'); // HalalVerificationState
            $table->text('evidence_summary')->nullable(); // public-safe
            $table->text('override_reason')->nullable(); // admin-only, never in public payloads
            $table->json('heuristic_matches')->nullable(); // [{field, term, strength}]
            $table->timestamp('effective_from');
            $table->timestamp('effective_until')->nullable();
            $table->foreignId('superseded_by_id')->nullable()->constrained('restaurant_halal_verifications')->nullOnDelete();
            $table->timestamps();

            $table->index(['restaurant_id', 'state']);
            $table->index(['restaurant_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_halal_verifications');
    }
};
