<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            // Pure provenance ("submitted by the community") — never an ownership/edit-rights
            // link. Null for every Google-synced/fixture row.
            $table->foreignId('source_submission_id')->nullable()->after('id')
                ->constrained('restaurant_submissions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_submission_id');
        });
    }
};
