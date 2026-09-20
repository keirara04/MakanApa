<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_field_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('restaurant_submission_id')->nullable()->constrained()->nullOnDelete();
            $table->string('field');
            // Real JSON type, not encoded text — a value can legitimately be a string/number/
            // object/array, and a JSON null here (row exists, value null) means "MakanApa
            // verified this should be blank," distinct from "no row = Google controls it."
            $table->jsonb('value');
            $table->string('authority'); // community_verified | admin (business_owner, later)
            // Nullable + nullOnDelete: the moderation event is historical and must survive a
            // staff account being removed — it shouldn't hold a delete hostage.
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at');
            $table->timestamps();
            $table->unique(['restaurant_id', 'field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_field_overrides');
    }
};
