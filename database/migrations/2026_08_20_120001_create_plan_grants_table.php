<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan entitlement that doesn't come from a Stripe subscription: today a
 * lifetime purchase (`mode=payment` Checkout, so no `customer.subscription.*`
 * webhook ever fires and nothing lands in `subscriptions`), tomorrow a comped
 * or promotional grant.
 *
 * Mirrors `credit_packs`' lifecycle: recorded `pending` at checkout, flipped
 * `active` on `checkout.session.completed`, `revoked` on refund or dispute.
 * `expires_at` is null for a grant that never lapses, which is every lifetime
 * purchase: it exists so a fixed-term comp doesn't need a second table.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plan_grants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('purchased_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source')->default('lifetime_purchase');
            $table->string('status')->default('pending');
            $table->unsignedInteger('price_cents')->default(0);
            $table->string('currency', 3)->default('usd');
            $table->string('stripe_checkout_session_id')->nullable();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('expiry_notified_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->index('stripe_payment_intent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_grants');
    }
};
