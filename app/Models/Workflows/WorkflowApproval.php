<?php

namespace App\Models\Workflows;

use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Backs the `HumanApproval` node type: one row per paused node, created when
 * the run reaches it, decided (or left pending) via
 * `WorkflowRunner::resolveApproval()`. See docs/WORKFLOWS_PLAN.md's
 * "Human-in-the-loop, waits, and dry runs" section. `run_id`/`node_run_id`/
 * `requested_at` are set at creation time by the engine (mirrors `NodeRun`'s
 * own create()-vs-forceFill() split); the decision fields are engine-only,
 * written via `forceFill()`.
 */
#[Fillable(['run_id', 'node_run_id', 'requested_at'])]
class WorkflowApproval extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }

    public function nodeRun(): BelongsTo
    {
        return $this->belongsTo(NodeRun::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isDecided(): bool
    {
        return $this->decided_at !== null;
    }
}
