<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The app's old "revoke" wrote status `revoked`, which the admin panel doesn't know (no filter,
 * no Reactivate). Revoke is a suspend now; move the existing rows over so they can be restored.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('status', 'revoked')->update(['status' => 'suspended']);
    }

    public function down(): void
    {
        // Not reversible: suspended users from before this can't be told apart.
    }
};
