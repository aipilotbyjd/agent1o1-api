<?php

namespace App\Models\Templates;

use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use Database\Factories\Templates\WorkflowTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A reusable workflow blueprint: its `graph` is the same
 * `{nodes, edges}` shape as `WorkflowVersion.graph`, materialized into a new
 * `Workflow` via `Workflow::replaceGraph()` when used. `workspace_id` null
 * means a global/system template visible to every workspace.
 */
#[Fillable(['workspace_id', 'source_workflow_id', 'created_by', 'name', 'slug', 'description', 'category', 'icon', 'color', 'graph', 'visibility', 'is_featured', 'sort_order'])]
class WorkflowTemplate extends Model
{
    /** @use HasFactory<WorkflowTemplateFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'visibility' => 'private',
        'usage_count' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'graph' => 'array',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
            'usage_count' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function sourceWorkflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'source_workflow_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPublic(): bool
    {
        return $this->visibility === 'public';
    }

    /**
     * A workspace's own templates plus every global (workspace_id null)
     * public template.
     */
    public function scopeVisibleTo(Builder $query, Workspace $workspace): Builder
    {
        return $query->where(function (Builder $query) use ($workspace): void {
            $query->where('workspace_id', $workspace->id)
                ->orWhere(function (Builder $query): void {
                    $query->whereNull('workspace_id')->where('visibility', 'public');
                });
        });
    }
}
