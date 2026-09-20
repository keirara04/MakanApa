<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_submissions', function (Blueprint $table) {
            $table->id();
            // Nullable + nullOnDelete, not cascade: deleting a contributor's account must never
            // cascade into deleting submission/provenance history, and must never conflict with
            // restaurants.source_submission_id's restrictOnDelete on the same delete.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('university_id')->nullable()->constrained()->restrictOnDelete();
            // Target for edit_place/closure; back-filled for new_place once approval creates or
            // links a canonical restaurant.
            $table->foreignId('restaurant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('submission_type'); // new_place | edit_place | closure
            $table->string('source_type'); // google | manual
            $table->string('google_place_id')->nullable();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('food_category')->nullable();
            $table->unsignedTinyInteger('price_level')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('location_source'); // google | current_location | map_pin
            $table->text('notes')->nullable();
            $table->string('status')->default('pending'); // pending | approved | rejected | changes_requested | cancelled
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_submissions');
    }
};
