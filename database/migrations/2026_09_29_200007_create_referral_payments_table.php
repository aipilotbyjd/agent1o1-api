<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every paid Stripe charge from a referred workspace, keyed by its invoice
 * or payment-intent id. It orders payments (first vs. repeat), makes a
 * redelivered webhook harmless, and maps a later refund or dispute (which
 * only carries a payment intent) back to the rewards that payment earned.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('referral_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('referral_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->string('payment_intent_id')->nullable()->index();
            $table->string('source');
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('usd');
            $table->foreignUuid('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('billing_interval')->nullable();
            $table->unsignedInteger('sequence');
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['referral_id', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_payments');
    }
};
