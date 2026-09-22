<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('category'); // account_admin | release_announcements — mirrors NotificationCategory
            // account_admin fields
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            // release_announcements fields
            $table->string('version')->nullable();
            $table->text('message')->nullable();
            $table->string('app_store_url')->nullable();
            $table->timestamp('send_at');
            $table->string('status')->default('pending'); // pending | sent | cancelled
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'send_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_notifications');
    }
};
