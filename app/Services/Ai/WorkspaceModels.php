<?php

namespace App\Services\Ai;

use App\Exceptions\ModelNotOnPlanException;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\WorkspaceModelSettings;
use App\Models\Billing\Plan;
use App\Models\Workspaces\Workspace;

/**
 * A workspace's default model and what it may pick, as every place that picks
 * or validates a model needs them. A model is allowed when the workspace's
 * admin has not narrowed the list to exclude it (see `WorkspaceModelSettings`)
 * and, if the model is limited to a plan, the workspace's plan is at least that
 * high (see `Plan::reaches()`).
 *
 * Picking is checked by the `AllowedModel` rule; running is checked again by
 * `ensurePlanAllows()`, so a workspace that downgrades loses a model it can no
 * longer pay for even in agents and workflows saved before the downgrade.
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
     * Whether the workspace's plan reaches the model's minimum plan.
     */
    public function planAllows(string $workspaceId, string $modelCatalogId): bool
    {
        $model = ModelCatalog::query()->with('minPlan')->find($modelCatalogId);

        return $model === null || $this->planAllowsModel(self::planFor($workspaceId), $model);
    }

    /**
     * Whether `$plan` (the workspace's current plan, null when it has none)
     * may use `$model`.
     */
    public function planAllowsModel(?Plan $plan, ModelCatalog $model): bool
    {
        $minimum = $this->requiredPlanFor($model);

        if ($minimum === null) {
            return true;
        }

        return $plan === null
            ? $minimum->creditsMonthly() <= 0
            : $plan->reaches($minimum);
    }

    /**
     * Throws when the workspace's plan does not include the model, for the
     * places that run a model (workflow AI steps, agent turns, the assistant)
     * rather than pick one. Internal entries (the platform's own helpers) and
     * slugs that match no catalog entry (raw provider model ids) are not
     * limited by plan.
     *
     * @throws ModelNotOnPlanException
     */
    public function ensurePlanAllows(string $workspaceId, ModelCatalog|string|null $model): void
    {
        $model = is_string($model)
            ? ModelCatalog::query()->with('minPlan')->where('slug', $model)->first()
            : $model;

        if ($model === null || $model->is_internal) {
            return;
        }

        $plan = self::planFor($workspaceId);

        if (! $this->planAllowsModel($plan, $model)) {
            throw new ModelNotOnPlanException($model, $this->requiredPlanFor($model));
        }
    }

    /**
     * The cheapest plan allowed to use `$model`: its own minimum plan, else
     * its cost tier's (`billing.model_access.min_plan_by_cost_tier`), else
     * null when every plan may use it.
     */
    public function requiredPlanFor(ModelCatalog $model): ?Plan
    {
        return $model->minPlan ?? $this->minimumPlanForCostTier($model->cost_tier);
    }

    /**
     * The cheapest plan allowed a model of this cost tier, or null when the
     * tier is open to every plan (or its configured plan does not exist).
     */
    public function minimumPlanForCostTier(?int $costTier): ?Plan
    {
        $slug = config("billing.model_access.min_plan_by_cost_tier.{$costTier}");

        if (! is_string($slug) || $slug === '') {
            return null;
        }

        return once(fn (): ?Plan => Plan::query()->where('slug', $slug)->first());
    }

    /**
     * The plan that applies to the workspace right now, or null when it has
     * none.
     */
    public static function planFor(string $workspaceId): ?Plan
    {
        return Workspace::query()->find($workspaceId)?->currentPlan();
    }
}
