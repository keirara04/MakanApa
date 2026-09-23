<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Evaluation only — Makan Brain never learns from these (passive signals are too noisy
        // to count as preference), they just feed the v1-vs-v2 evaluation page.
        Schema::create('decision_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('decision_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // detail_opened | directions_opened | reasons_expanded | what_if_opened | reopened
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['decision_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decision_interactions');
    }
};
