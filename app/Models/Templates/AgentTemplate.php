<?php

namespace App\Models\Templates;

use App\Models\Agents\Agent;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Templates\AgentTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A reusable agent blueprint. `config` holds
 * `{instructions, provider, model, temperature, settings, tool_bindings,
 * workflow_ids, skill_ids}`: the same fields `AgentVersion.snapshot` is
 * meant to carry, materialized into a new `Agent` when used. `workspace_id`
 * null means a global/system template visible to every workspace.
 */
#[Fillable(['workspace_id', 'source_agent_id', 'created_by', 'name', 'slug', 'description', 'category', 'icon', 'color', 'config', 'visibility', 'is_featured', 'sort_order'])]
class AgentTemplate extends Model
{
    /** @use HasFactory<AgentTemplateFactory> */
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
            'config' => 'array',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
            'usage_count' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function sourceAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'source_agent_id');
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
