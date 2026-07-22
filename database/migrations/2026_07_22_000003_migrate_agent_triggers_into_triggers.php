<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data migration: copy legacy `agent_triggers` rows into the unified `triggers`
 * table as agent-targeted triggers. Idempotent and safe on a fresh (empty)
 * database — the source table is dropped by a later contract migration.
 *
 * `webhook_uuid` is set to the original agent-trigger id so existing public
 * webhook URLs keep resolving through the unified /webhooks/{uuid} endpoint.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('agent_triggers')) {
            return;
        }

        DB::table('agent_triggers')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                if (DB::table('triggers')->where('id', $row->id)->exists()) {
                    continue;
                }

                $config = $row->config ? json_decode($row->config, true) : [];
                $type = $row->type === 'schedule' ? 'scheduled' : $row->type;

                DB::table('triggers')->insert([
                    'id' => $row->id,
                    'target_type' => 'agent',
                    'target_id' => $row->agent_id,
                    'workflow_id' => null,
                    'workspace_id' => $row->workspace_id,
                    'name' => ucfirst($row->type).' trigger',
                    'type' => $type,
                    'is_active' => $row->is_active,
                    'is_paused' => false,
                    'webhook_uuid' => $type === 'webhook' ? $row->id : null,
                    'webhook_status' => $type === 'webhook' ? 'active' : null,
                    'schedule_expression' => $config['cron'] ?? null,
                    'schedule_timezone' => $config['timezone'] ?? 'UTC',
                    'max_concurrency' => 1,
                    'total_events' => 0,
                    'total_executions' => 0,
                    'settings' => $row->config,
                    'initial_message' => $row->initial_message,
                    'last_fired_at' => $row->last_fired_at,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        });
    }

    public function down(): void
    {
        // Non-reversible data move; the source table is restored by rolling back
        // the contract migration that drops it.
    }
};
