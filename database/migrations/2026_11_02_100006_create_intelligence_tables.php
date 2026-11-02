<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The analytics layer the admin intelligence reads, so reports never scan the
 * raw ledgers on every page load:
 *
 * - `fact_usage_daily`: AI calls rolled up by day, workspace, feature and
 *   model, from `ai_call_events` (kept after the raw events are pruned);
 * - `fact_workspace_daily`: each workspace's day: plan, credits, revenue,
 *   provider cost and margin, from `credit_transactions` and the call ledger;
 * - `predictions`: every forecast made, with its inputs, and the actual value
 *   once known, so the accuracy of each forecast can be shown;
 * - `admin_recommendations`: proposed actions with their evidence, and who
 *   applied or dismissed them;
 * - `experiments` / `experiment_assignments`: A/B tests and which variant
 *   each workspace is in.
 *
 * All fact tables are rebuilt a day at a time, so a rebuild is always safe to
 * run again.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fact_usage_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->uuid('workspace_id')->nullable();
            $table->string('feature', 64);
            $table->string('provider');
            $table->string('model');
            $table->boolean('is_byok')->default(false);
            $table->unsignedInteger('calls')->default(0);
            $table->unsignedInteger('failed_calls')->default(0);
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('cache_read_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->decimal('provider_cost_usd', 16, 6)->default(0);
            $table->decimal('untrusted_cost_usd', 16, 6)->default(0);
            $table->unsignedInteger('avg_latency_ms')->nullable();

            $table->index(['date', 'workspace_id']);
            $table->index(['date', 'provider', 'model']);
            $table->index(['date', 'feature']);
        });

        Schema::create('fact_workspace_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->uuid('workspace_id');
            $table->string('plan_slug')->nullable();
            $table->boolean('is_paying')->default(false);
            $table->unsignedBigInteger('credits_used')->default(0);
            $table->decimal('revenue_usd', 14, 6)->default(0);
            $table->decimal('provider_cost_usd', 16, 6)->default(0);
            $table->decimal('margin_usd', 16, 6)->default(0);
            $table->unsignedInteger('ai_calls')->default(0);
            $table->unsignedInteger('failed_calls')->default(0);

            $table->unique(['date', 'workspace_id']);
            $table->index(['workspace_id', 'date']);
            $table->index(['date', 'plan_slug']);
        });

        Schema::create('predictions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kind', 64);
            $table->string('subject_type', 64)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->date('made_on');
            $table->date('target_date');
            $table->decimal('value', 18, 6);
            $table->decimal('lower', 18, 6)->nullable();
            $table->decimal('upper', 18, 6)->nullable();
            $table->decimal('actual', 18, 6)->nullable();
            $table->json('inputs')->nullable();
            $table->timestamps();

            $table->unique(['kind', 'subject_type', 'subject_id', 'made_on', 'target_date'], 'predictions_unique');
            $table->index(['kind', 'target_date']);
        });

        Schema::create('admin_recommendations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('rule', 64);
            $table->string('fingerprint')->unique();
            $table->string('severity', 16);
            $table->string('subject_type', 64)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->string('title');
            $table->text('body');
            $table->json('evidence')->nullable();
            $table->json('action')->nullable();
            $table->string('status', 16)->default('open');
            $table->string('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->index(['status', 'severity']);
        });

        Schema::create('experiments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('hypothesis')->nullable();
            $table->json('variants');
            $table->string('guard_metric', 64)->default('gross_margin');
            $table->decimal('guard_threshold', 10, 4)->nullable();
            $table->string('status', 16)->default('draft');
            $table->string('stopped_reason', 500)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->timestamps();
        });

        Schema::create('experiment_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('experiment_id');
            $table->uuid('workspace_id');
            $table->string('variant', 64);
            $table->timestamp('assigned_at');

            $table->unique(['experiment_id', 'workspace_id']);
            $table->index(['experiment_id', 'variant']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('experiment_assignments');
        Schema::dropIfExists('experiments');
        Schema::dropIfExists('admin_recommendations');
        Schema::dropIfExists('predictions');
        Schema::dropIfExists('fact_workspace_daily');
        Schema::dropIfExists('fact_usage_daily');
    }
};
