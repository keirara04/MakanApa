<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An admin may confirm a place is halal certified without recording the certificate's
        // private details (number / expiry). The authority alone is still worth keeping.
        Schema::table('restaurant_halal_certificates', function (Blueprint $table) {
            $table->string('certificate_number')->nullable()->change();
            $table->date('expires_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_halal_certificates', function (Blueprint $table) {
            $table->string('certificate_number')->nullable(false)->change();
            $table->date('expires_at')->nullable(false)->change();
        });
    }
};
