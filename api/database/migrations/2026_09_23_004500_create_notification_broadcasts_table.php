<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            // account_admin fields
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            // release_announcements fields
            $table->string('version')->nullable();
            $table->text('message')->nullable();
            $table->string('app_store_url')->nullable();
            $table->string('source')->default('manual'); // manual | scheduled | command
            $table->foreignId('scheduled_notification_id')->nullable()->constrained('scheduled_notifications')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('recipients_considered')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_broadcasts');
    }
};
