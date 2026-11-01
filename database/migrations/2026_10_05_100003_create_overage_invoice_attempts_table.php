<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per attempt to invoice overage: written in the same transaction
 * that marks the credits billed, so a crash before or during the Stripe call
 * leaves a `pending` record to reconcile instead of credits that are billed
 * on paper only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overage_invoice_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('credits');
            $table->unsignedBigInteger('amount_cents');
            $table->json('allocations');
            $table->string('stripe_invoice_id')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overage_invoice_attempts');
    }
};
