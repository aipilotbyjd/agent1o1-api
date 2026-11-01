<?php

namespace App\Services\Ai;

use App\Models\Ai\ModelCatalog;
use App\Models\Ai\WorkspaceModelSettings;
use App\Models\Workspaces\Workspace;

/**
 * A workspace's default model and what it may pick, as every place that picks
 * or validates a model needs them. A model is allowed when the workspace's
 * admin has not narrowed the list to exclude it (see `WorkspaceModelSettings`)
 * and, if the model is limited to a plan, the workspace's plan is at least that
 * generous.
 */
class WorkspaceModels
{
    /**
     * The workspace's default model, when it has one that is still usable
     * (active, public and allowed).
     */
    public function default(string $workspaceId): ?ModelCatalog
    {
        $settings = WorkspaceModelSettings::forWorkspace($workspaceId);

        if ($settings->default_model_catalog_id === null || ! $this->allows($workspaceId, $settings->default_model_catalog_id)) {
            return null;
        }

        return ModelCatalog::query()
            ->whereKey($settings->default_model_catalog_id)
            ->where('is_active', true)
            ->where('is_internal', false)
            ->first();
    }

    public function allows(string $workspaceId, string $modelCatalogId): bool
    {
        return WorkspaceModelSettings::forWorkspace($workspaceId)->allows($modelCatalogId)
            && $this->planAllows($workspaceId, $modelCatalogId);
    }

    public function allowsSlug(string $workspaceId, string $slug): bool
    {
        $id = ModelCatalog::query()->where('slug', $slug)->value('id');

        return $id === null || $this->allows($workspaceId, $id);
    }

    /**
     * Whether the workspace's plan reaches the model's minimum plan. Plans are
     * compared by monthly credits, the measure `Workspace::currentPlan()` uses
     * to pick the more generous of a subscription and a grant. A model with no
     * minimum is open to every plan.
     */
    public function planAllows(string $workspaceId, string $modelCatalogId): bool
    {
        $minimum = ModelCatalog::query()->with('minPlan')->find($modelCatalogId)?->minPlan;

        if ($minimum === null) {
            return true;
        }

        return self::planCredits($workspaceId) >= $minimum->creditsMonthly();
    }

    /**
     * The monthly credits of the plan that applies to the workspace right now,
     * or zero when it has none.
     */
    public static function planCredits(string $workspaceId): int
    {
        return Workspace::query()->find($workspaceId)?->currentPlan()?->creditsMonthly() ?? 0;
    }
}
