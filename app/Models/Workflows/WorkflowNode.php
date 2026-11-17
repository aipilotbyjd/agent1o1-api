<?php

namespace App\Models\Workflows;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workflow_id', 'key', 'type', 'config', 'position', 'pinned_data', 'pinned_at', 'pinned_by'])]
class WorkflowNode extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'position' => 'array',
            'pinned_data' => 'array',
            'pinned_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pinnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pinned_by');
    }

    public function isPinned(): bool
    {
        return $this->pinned_data !== null;
    }

    /**
     * @return HasMany<WorkflowEdge, $this>
     */
    public function outgoingEdges(): HasMany
    {
        return $this->hasMany(WorkflowEdge::class, 'from_node_id');
    }

    /**
     * @return HasMany<WorkflowEdge, $this>
     */
    public function incomingEdges(): HasMany
    {
        return $this->hasMany(WorkflowEdge::class, 'to_node_id');
    }
}
