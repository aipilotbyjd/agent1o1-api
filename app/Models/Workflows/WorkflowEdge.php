<?php

namespace App\Models\Workflows;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `condition` semantics (see docs/WORKFLOWS_PLAN.md's `workflow_edges`
 * section): `null` = unconditional, a literal value = "follow only if the
 * source node's `result` output equals this", `ERROR_CONDITION` = "follow
 * only if the source node failed".
 */
#[Fillable(['workflow_id', 'from_node_id', 'to_node_id', 'condition'])]
class WorkflowEdge extends Model
{
    use HasUuids;

    public const string ERROR_CONDITION = 'error';

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function fromNode(): BelongsTo
    {
        return $this->belongsTo(WorkflowNode::class, 'from_node_id');
    }

    public function toNode(): BelongsTo
    {
        return $this->belongsTo(WorkflowNode::class, 'to_node_id');
    }

    public function isErrorEdge(): bool
    {
        return $this->condition === self::ERROR_CONDITION;
    }
}
