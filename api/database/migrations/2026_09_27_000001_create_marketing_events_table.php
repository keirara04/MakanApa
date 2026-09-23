<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Anonymous landing-page funnel counts: no IP, user agent, or cookie is stored — just
        // "a view happened" / "a download button was clicked, from where".
        Schema::create('marketing_events', function (Blueprint $table) {
            $table->id();
            $table->string('event'); // landing_view | testflight_click
            $table->string('source')->nullable(); // nav | hero | qr | final | faq (testflight_click only)
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_events');
    }
};
