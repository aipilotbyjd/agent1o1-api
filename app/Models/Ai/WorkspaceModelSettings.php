<?php

namespace App\Models\Ai;

use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An admin's model choices for the workspace: the model new agents, skills
 * and workflow AI steps start on (`default_model_catalog_id`), and (when
 * set) the only catalog models members may pick
 * (`allowed_model_catalog_ids`; null means every model). Applied through
 * `Services\Ai\WorkspaceModels`. `forWorkspace()` returns an unsaved
 * default when none has been set.
 */
#[Fillable(['workspace_id', 'default_model_catalog_id', 'allowed_model_catalog_ids'])]
class WorkspaceModelSettings extends Model
{
    use HasUuids;

    protected $table = 'workspace_model_settings';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allowed_model_catalog_ids' => 'array',
        ];
    }

    public static function forWorkspace(string $workspaceId): self
    {
        return self::query()->where('workspace_id', $workspaceId)->first()
            ?? new self(['workspace_id' => $workspaceId]);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function defaultModel(): BelongsTo
    {
        return $this->belongsTo(ModelCatalog::class, 'default_model_catalog_id');
    }

    public function allows(string $modelCatalogId): bool
    {
        return $this->allowed_model_catalog_ids === null || in_array($modelCatalogId, $this->allowed_model_catalog_ids, true);
    }
}
