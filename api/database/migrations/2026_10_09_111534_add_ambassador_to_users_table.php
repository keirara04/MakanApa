<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ambassador of at most one community — a university or an area — set by an admin, independent of
 * the user's own affiliation. "Not both" is enforced in AdminUserService::setAmbassador().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('ambassador_university_id')->nullable()->constrained('universities')->nullOnDelete();
            $table->foreignId('ambassador_area_id')->nullable()->constrained('areas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ambassador_university_id');
            $table->dropConstrainedForeignId('ambassador_area_id');
        });
    }
};
