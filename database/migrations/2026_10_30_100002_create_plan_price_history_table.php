<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Every Stripe price a plan has ever sold on. Stripe prices are
     * immutable, so changing a plan's amount means a new price: the old id
     * is kept here so a subscriber still paying it keeps resolving to their
     * plan (see `Plan::findByStripePriceId()`).
     */
    public function up(): void
    {
        Schema::create('plan_price_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_id')->constrained()->cascadeOnDelete();
            $table->string('interval', 20);
            $table->string('stripe_price_id')->unique();
            $table->unsignedInteger('amount_cents');
            $table->timestamp('retired_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['plan_id', 'interval']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_price_history');
    }
};
