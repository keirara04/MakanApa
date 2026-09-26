<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('search_misses', function (Blueprint $table) {
            // Set when an admin has dealt with the miss (added the place, or decided it isn't
            // one). A miss that keeps happening after that (last_seen_at > resolved_at) shows as
            // open again — the fix evidently didn't take.
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('search_misses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resolved_by');
            $table->dropColumn('resolved_at');
        });
    }
};
