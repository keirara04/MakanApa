<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Makan sini" from search: a Decision with mode=search. client_choice_id is the client's retry
 * key (unique, so a retried request can never create a second accepted decision) and
 * search_context snapshots what the user searched ({query, radiusKm, source}).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('decisions', function (Blueprint $table) {
            $table->uuid('client_choice_id')->nullable()->unique();
            $table->json('search_context')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('decisions', function (Blueprint $table) {
            $table->dropUnique(['client_choice_id']);
            $table->dropColumn(['client_choice_id', 'search_context']);
        });
    }
};
