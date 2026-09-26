<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Filament's notification bell queries `data->format`, which Postgres only allows on a
        // json column — the stock Laravel migration made it text. Existing rows are JSON already.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE json USING data::json');
        }

        Schema::table('restaurant_submissions', function (Blueprint $table) {
            // When it last entered the review queue (created_at is when the draft started) —
            // what the moderation response-time widget and alerts measure from.
            $table->timestamp('submitted_at')->nullable();
            $table->index(['status', 'submitted_at']);
        });

        DB::table('restaurant_submissions')
            ->where('status', '!=', 'draft')
            ->update(['submitted_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('restaurant_submissions', function (Blueprint $table) {
            $table->dropIndex(['status', 'submitted_at']);
            $table->dropColumn('submitted_at');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE text USING data::text');
        }
    }
};
