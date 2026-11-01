<?php

namespace App\Models\Agents;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per built-in/custom node attached to an `Agent` as a tool: see
 * the `agent_node` migration's docblock for the security-boundary design
 * this carries forward unchanged from the old project. Named
 * `AgentToolBinding` (not `AgentModelNode`/`AgentNode`) to avoid colliding
 * with `Nodes/AiAutomation/AgentNode` (the *workflow* node type that embeds
 * an agent: a different concept entirely).
 */
#[Fillable(['agent_id', 'node_type', 'config', 'exposed_fields', 'approval_policy'])]
class AgentToolBinding extends Model
{
    use HasUuids;

    protected $table = 'agent_node';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'exposed_fields' => 'array',
            'approval_policy' => 'array',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
