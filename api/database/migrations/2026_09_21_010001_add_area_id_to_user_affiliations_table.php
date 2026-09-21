<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_affiliations', function (Blueprint $table) {
            // Restrict, not nullOnDelete — areas are historical reference data; retire one via
            // `active = false` rather than deleting it out from under existing affiliations.
            $table->foreignId('area_id')->nullable()->after('university_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_affiliations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('area_id');
        });
    }
};
