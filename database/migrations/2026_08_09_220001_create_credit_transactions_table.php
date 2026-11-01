<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An append-only usage ledger: one row per node execution or agent turn
 * that consumed credits. `source_type`/`source_id` point at the `NodeRun`
 * or `AgentMessage` (the assistant reply) that caused the charge. See
 * docs/PLAN.md's "Architecture Overview" `CreditTransaction` entry and
 * docs/WORKFLOWS_AGENTS_BUILD_PLAN.md Stage 8.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('usage_period_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_type');
            $table->uuid('source_id');
            $table->unsignedInteger('credits');
            $table->unsignedInteger('topup_credits')->default(0);
            $table->string('reason')->nullable();
            $table->unsignedInteger('overage_credits')->default(0);
            $table->timestamps();

            $table->index(['workspace_id', 'created_at']);
            $table->unique(['source_type', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
