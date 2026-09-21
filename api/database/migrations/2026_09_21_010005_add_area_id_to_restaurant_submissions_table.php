<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_submissions', function (Blueprint $table) {
            $table->foreignId('area_id')->nullable()->after('university_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('area_id');
        });
    }
};
