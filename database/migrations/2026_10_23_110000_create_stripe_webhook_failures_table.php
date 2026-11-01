<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stripe events our handler could not process. A failed event leaves no trace
 * in `processed_webhook_events` (its claim is released so Stripe can retry),
 * so without this a paid checkout whose handler kept failing, and which Stripe
 * eventually stopped retrying, would simply be lost. The payload is not kept:
 * a replay fetches the event from Stripe again.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stripe_webhook_failures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('stripe_event_id')->unique();
            $table->string('type');
            $table->text('error');
            $table->unsignedInteger('attempts')->default(1);
            $table->timestamp('first_failed_at');
            $table->timestamp('last_failed_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['resolved_at', 'last_failed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stripe_webhook_failures');
    }
};
