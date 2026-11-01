<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A rolling window (monthly, by default: see `Workspace::currentUsagePeriod()`)
 * a workspace's `credit_transactions` roll up against. `credits_limit` stays
 * `null` (unlimited) until Stripe plan tiers are wired: see docs/PLAN.md's
 * Phase 7 for the deferred 75%/90% threshold alerts and plan-tier work this
 * table is scoped down from for now.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('usage_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedInteger('credits_used')->default(0);
            $table->unsignedInteger('credits_limit')->nullable();
            $table->unsignedInteger('overage_credits_used')->default(0);
            $table->unsignedInteger('overage_credits_billed')->default(0);
            $table->timestamps();

            $table->unique(['workspace_id', 'starts_at']);
            $table->index(['workspace_id', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_periods');
    }
};
