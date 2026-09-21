<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('installation_id')->nullable();
            $table->timestamp('started_at')->useCurrent();
            // Nullable, not deleted: a session with no ended_at yet means the client never got
            // to send the close event (killed, crashed, network down) — still useful as "opened
            // the app at X", just without a duration.
            $table->timestamp('ended_at')->nullable();

            $table->index(['user_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_sessions');
    }
};
