<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_deletions', function (Blueprint $table) {
            $table->id();
            // No FK / no cascade on purpose — the user row this refers to is gone by the time
            // this row is read. user_id is kept only as a best-effort cross-reference (e.g. to
            // other tables' now-orphaned rows), email is the durable record of who this was.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('email');
            $table->timestamp('deleted_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deletions');
    }
};
