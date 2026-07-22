<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copy legacy `executions` and `agent_runs` rows into the unified `runs` table,
 * preserving ids so existing child rows (execution_nodes, ai_agent_steps, …)
 * still resolve. Safe/no-op on a fresh database. A later migration drops the
 * source tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('executions')) {
            DB::table('executions')->orderBy('id')->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    if (DB::table('runs')->where('id', $row->id)->exists()) {
                        continue;
                    }

                    DB::table('runs')->insert([
                        'id' => $row->id,
                        'runnable_type' => 'workflow',
                        'runnable_id' => $row->workflow_id,
                        'workflow_id' => $row->workflow_id,
                        'workspace_id' => $row->workspace_id,
                        'status' => $row->status,
                        'wait_token' => $row->wait_token,
                        'mode' => $row->mode,
                        'triggered_by' => $row->triggered_by,
                        'started_at' => $row->started_at,
                        'finished_at' => $row->finished_at,
                        'duration_ms' => $row->duration_ms,
                        'trigger_data' => $row->trigger_data,
                        'result_data' => $row->result_data,
                        'error' => $row->error,
                        'attempt' => $row->attempt,
                        'max_attempts' => $row->max_attempts,
                        'retry_delay_seconds' => $row->retry_delay_seconds,
                        'parent_execution_id' => $row->parent_execution_id,
                        'credits_consumed' => $row->credits_consumed,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]);
                }
            });
        }

        if (Schema::hasTable('agent_runs')) {
            DB::table('agent_runs')->orderBy('id')->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    if (DB::table('runs')->where('id', $row->id)->exists()) {
                        continue;
                    }

                    DB::table('runs')->insert([
                        'id' => $row->id,
                        'runnable_type' => 'agent',
                        'runnable_id' => $row->agent_id,
                        'agent_id' => $row->agent_id,
                        'workspace_id' => $row->workspace_id,
                        'user_id' => $row->user_id,
                        'conversation_id' => $row->conversation_id,
                        'trigger_id' => $row->trigger_id,
                        'source' => $row->source,
                        'status' => $row->status,
                        'input' => $row->input,
                        'output' => $row->output,
                        'plan' => $row->plan ?? null,
                        'reflections' => $row->reflections ?? null,
                        'error' => $row->error,
                        'provider' => $row->provider,
                        'model' => $row->model,
                        'prompt_tokens' => $row->prompt_tokens,
                        'completion_tokens' => $row->completion_tokens,
                        'total_tokens' => $row->total_tokens,
                        'estimated_cost' => $row->estimated_cost ?? null,
                        'duration_ms' => $row->duration_ms,
                        'metadata' => $row->metadata,
                        'started_at' => $row->started_at,
                        'finished_at' => $row->finished_at,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]);
                }
            });
        }
    }

    public function down(): void
    {
        // Non-reversible data move; source tables are restored by rolling back
        // the contract migration that drops them.
    }
};
