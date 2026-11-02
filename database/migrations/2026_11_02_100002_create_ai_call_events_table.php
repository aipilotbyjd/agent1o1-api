<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per request the platform sent to an AI provider: every step of an
 * agent loop, every embeddings, image, audio and transcription call, and the
 * ones that failed or were failed over. Append-only. It is the cost side of
 * the books (what each call cost the platform, at which price version); the
 * revenue side is `credit_transactions.revenue_usd`.
 *
 * Rows outlive the runs and sessions they belong to: nothing here cascades,
 * so deleting a workflow never rewrites what was spent.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_call_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestamp('occurred_at')->index();
            $table->uuid('workspace_id')->nullable();
            $table->string('user_id')->nullable();
            $table->string('feature', 64);
            $table->string('agent_class')->nullable();
            $table->string('kind', 32);
            $table->string('invocation_id', 64)->nullable()->index();
            $table->unsignedSmallInteger('step')->nullable();
            $table->uuid('run_id')->nullable()->index();
            $table->string('subject_type', 64)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->string('trace_id', 64)->nullable()->index();
            $table->string('provider');
            $table->string('model');
            $table->boolean('is_byok')->default(false);
            $table->string('status', 32);
            $table->string('error_class')->nullable();
            $table->string('error_message', 500)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('cache_read_tokens')->default(0);
            $table->unsignedBigInteger('cache_write_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('reasoning_tokens')->default(0);
            $table->unsignedInteger('units')->nullable();
            $table->decimal('provider_cost_usd', 16, 8)->default(0);
            $table->string('price_status', 32);
            $table->uuid('price_version_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['workspace_id', 'occurred_at']);
            $table->index(['provider', 'model', 'occurred_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['feature', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_call_events');
    }
};
