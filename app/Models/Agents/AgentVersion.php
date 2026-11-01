<?php

namespace App\Models\Agents;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Schema-only for now: see the migration's docblock. `snapshot` will hold
 * `instructions`/`provider`/`model`/`temperature`/`settings` plus attached
 * tool config as one immutable JSON blob, mirroring `WorkflowVersion.graph`.
 */
#[Fillable(['agent_id', 'version', 'snapshot', 'changed_by'])]
class AgentVersion extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
