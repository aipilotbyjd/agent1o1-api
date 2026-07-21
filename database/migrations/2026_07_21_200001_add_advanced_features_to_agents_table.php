<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Advanced agent capabilities from docs/AGENTS_ADVANCED_ROADMAP.md — reasoning
 * (planner/reflection), sub-agent delegation, long-horizon memory, extra tools,
 * and ops guardrails (budgets, moderation). Everything defaults off so existing
 * agents behave exactly as before until a feature is explicitly enabled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            // Phase 1 — intelligence & reasoning
            $table->boolean('planning_enabled')->default(false)->after('max_steps');
            $table->boolean('reflection_enabled')->default(false)->after('planning_enabled');
            $table->unsignedTinyInteger('reflection_interval')->default(1)->after('reflection_enabled');
            $table->json('child_agent_ids')->nullable()->after('default_workflow_id');
            $table->boolean('memory_auto_extract')->default(false)->after('child_agent_ids');
            $table->boolean('memory_semantic_recall')->default(false)->after('memory_auto_extract');
            $table->unsignedSmallInteger('memory_recall_limit')->default(6)->after('memory_semantic_recall');

            // Phase 2 — tooling & integrations
            $table->boolean('code_execution_enabled')->default(false)->after('memory_recall_limit');
            $table->boolean('web_browsing_enabled')->default(false)->after('code_execution_enabled');
            $table->boolean('tool_cache_enabled')->default(false)->after('web_browsing_enabled');

            // Phase 3 — ops & reliability
            $table->json('guardrails')->nullable()->after('tool_cache_enabled');
            $table->unsignedInteger('max_tokens_per_run')->nullable()->after('guardrails');
            $table->unsignedBigInteger('daily_token_budget')->nullable()->after('max_tokens_per_run');
            $table->decimal('daily_cost_budget', 10, 4)->nullable()->after('daily_token_budget');
            $table->boolean('is_paused')->default(false)->after('daily_cost_budget');
            $table->string('paused_reason')->nullable()->after('is_paused');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn([
                'planning_enabled',
                'reflection_enabled',
                'reflection_interval',
                'child_agent_ids',
                'memory_auto_extract',
                'memory_semantic_recall',
                'memory_recall_limit',
                'code_execution_enabled',
                'web_browsing_enabled',
                'tool_cache_enabled',
                'guardrails',
                'max_tokens_per_run',
                'daily_token_budget',
                'daily_cost_budget',
                'is_paused',
                'paused_reason',
            ]);
        });
    }
};
