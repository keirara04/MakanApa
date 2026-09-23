<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only experiment log for the Judgment System: one row per run. After insert,
        // only outcome/outcome_at (calibration labels) and the state snapshot purge may change.
        Schema::create('ai_judgments', function (Blueprint $table) {
            $table->id();
            $table->string('run_id', 40)->unique();
            $table->string('purpose')->index();
            $table->unsignedInteger('definition_version');
            $table->string('provider');
            $table->string('model');
            $table->string('structured_mode');
            $table->decimal('temperature', 3, 2);
            $table->unsignedTinyInteger('samples');
            $table->nullableMorphs('subject');
            $table->string('idempotency_key')->nullable()->index();
            $table->json('state')->nullable(); // sanitized snapshot, purged after retention
            $table->string('state_hash', 64);
            $table->json('questions');
            $table->json('answers')->nullable();
            $table->json('attempts')->nullable(); // one entry per provider request (samples + retries)
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->string('status'); // ok | failed | budget_exhausted | disabled
            $table->string('failure_reason')->nullable();
            $table->text('error')->nullable();
            $table->json('outcome')->nullable();
            $table->timestamp('outcome_at')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['purpose', 'definition_version', 'status']);
        });

        // A retried/duplicated job can't produce two successful judgments for the same subject+version.
        DB::statement("create unique index ai_judgments_idempotency_ok_unique on ai_judgments (idempotency_key) where status = 'ok'");
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_judgments');
    }
};
