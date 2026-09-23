<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_photos', function (Blueprint $table) {
            // sha256 of the original upload — flags the same image reused across submissions.
            $table->string('content_hash', 64)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_photos', function (Blueprint $table) {
            $table->dropIndex(['content_hash']);
            $table->dropColumn('content_hash');
        });
    }
};
