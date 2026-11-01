<?php

namespace App\Models\Templates;

use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Templates\TemplateCollectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A "pack" bundling any mix of `WorkflowTemplate` and `AgentTemplate` rows:
 * see `TemplateCollectionItem` for the polymorphic membership row.
 * `workspace_id` null means a global/system pack visible to every workspace.
 */
#[Fillable(['workspace_id', 'created_by', 'name', 'slug', 'description', 'category', 'icon', 'color', 'visibility'])]
class TemplateCollection extends Model
{
    /** @use HasFactory<TemplateCollectionFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'visibility' => 'private',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TemplateCollectionItem::class, 'collection_id')->orderBy('position');
    }

    public function isPublic(): bool
    {
        return $this->visibility === 'public';
    }

    /**
     * A workspace's own packs plus every global (workspace_id null) public pack.
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
