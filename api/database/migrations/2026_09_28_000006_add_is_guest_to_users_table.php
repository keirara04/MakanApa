<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Guest accounts (App Review 5.1.1(v): suggestions can't sit behind registration)
            // carry no identity at all. Postgres' unique index allows any number of NULLs.
            $table->string('email')->nullable()->change();
            $table->boolean('is_guest')->default(false)->index();
        });
    }

    /**
     * Not lossless: guest rows have no email, so they must go before email can be NOT NULL
     * again — rolling back the guest feature removes the guests (and cascades their data).
     */
    public function down(): void
    {
        DB::table('users')->where('is_guest', true)->delete();

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_guest']);
            $table->dropColumn('is_guest');
            $table->string('email')->nullable(false)->change();
        });
    }
};
