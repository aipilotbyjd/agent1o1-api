<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One-time credit top-ups purchased via Stripe Checkout (mode=payment),
 * separate from the subscription-driven `usage_periods.credits_limit`.
 * Activating a pack (on `checkout.session.completed`) adds its
 * `credits_amount` onto the workspace's *current* usage period limit:
 * see `Actions\Billing\ActivateCreditPackAction`.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('credit_packs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('purchased_by')->constrained('users');
            $table->string('pack_key');
            $table->unsignedInteger('credits_amount');
            $table->unsignedInteger('price_cents');
            $table->string('currency', 3)->default('usd');
            $table->string('stripe_checkout_session_id')->nullable();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('purchased_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_packs');
    }
};
