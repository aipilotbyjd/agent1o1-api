<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'workspace_id',
    'folder_id',
    'created_by',
    'name',
    'description',
    'icon',
    'color',
    'is_active',
    'is_locked',
    'current_version_id',
    'error_workflow_id',
    'max_concurrent_executions',
    'execution_count',
    'last_executed_at',
    'success_rate',
])]
class Workflow extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_locked' => 'boolean',
            'max_concurrent_executions' => 'integer',
            'execution_count' => 'integer',
            'last_executed_at' => 'datetime',
            'success_rate' => 'decimal:2',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(WorkflowVersion::class)->orderByDesc('version_number');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(Execution::class)->orderByDesc('created_at');
    }

    public function triggers(): HasMany
    {
        return $this->hasMany(Trigger::class);
    }

    public function stickyNotes(): HasMany
    {
        return $this->hasMany(StickyNote::class);
    }

    public function pinnedData(): HasMany
    {
        return $this->hasMany(PinnedNodeData::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(WorkflowShare::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(WorkflowApproval::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(WorkflowContractSnapshot::class);
    }

    public function credentials(): BelongsToMany
    {
        return $this->belongsToMany(Credential::class, 'credential_workflow');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'tag_workflow');
    }

    public function errorWorkflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'error_workflow_id');
    }

    /**
     * @return HasMany<Workflow, $this>
     */
    public function errorWorkflowsFrom(): HasMany
    {
        return $this->hasMany(Workflow::class, 'error_workflow_id');
    }

    public function incrementExecutionCount(): void
    {
        $this->increment('execution_count');
        $this->update(['last_executed_at' => now()]);
    }
}
