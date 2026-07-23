<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contract step: repoint every run child from the legacy executions /
 * agent_runs tables to the unified `runs` table, then drop the legacy tables.
 * Row ids were preserved by the data migration, so the existing execution_id /
 * agent_run_id values already reference the right `runs` rows.
 */
return new class extends Migration
{
    /** @var array<string, string> child table => on-delete behaviour for execution_id */
    private array $executionChildren = [
        'execution_nodes' => 'cascade',
        'execution_checkpoints' => 'cascade',
        'execution_logs' => 'cascade',
        'ai_fix_suggestions' => 'cascade',
        'execution_replay_packs' => 'null',
        'ai_agent_steps' => 'cascade',
    ];

    /** @var array<string, string> child table => on-delete behaviour for agent_run_id */
    private array $agentRunChildren = [
        'ai_agent_steps' => 'cascade',
        'agent_message_requests' => 'null',
        'artifacts' => 'null',
    ];

    public function up(): void
    {
        foreach ($this->executionChildren as $child => $onDelete) {
            $this->repoint($child, 'execution_id', $onDelete);
        }

        foreach ($this->agentRunChildren as $child => $onDelete) {
            $this->repoint($child, 'agent_run_id', $onDelete);
        }

        Schema::dropIfExists('agent_runs');
        Schema::dropIfExists('executions');
    }

    private function repoint(string $child, string $column, string $onDelete): void
    {
        if (! Schema::hasColumn($child, $column)) {
            return;
        }

        Schema::table($child, function (Blueprint $table) use ($column, $onDelete) {
            $table->dropForeign([$column]);
            $fk = $table->foreign($column)->references('id')->on('runs');
            $onDelete === 'null' ? $fk->nullOnDelete() : $fk->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Irreversible in practice: recreating the split tables and moving rows
        // back is out of scope. Roll back the runs feature in a fresh
        // environment instead.
    }
};
