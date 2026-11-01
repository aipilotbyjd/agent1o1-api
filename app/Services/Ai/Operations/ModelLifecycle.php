<?php

namespace App\Services\Ai\Operations;

use App\Exceptions\ModelLifecycleException;
use App\Models\Agents\Agent;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\WorkspaceModelSettings;
use App\Models\Assistant\Assistant;
use App\Services\Ai\ModelCatalogResolver;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Taking a model out of service without breaking what uses it:
 *
 *  - `deprecate`: announce a retirement date and a replacement; nothing
 *    changes until the date.
 *  - `retire`: switch the model off and move everything that picked it
 *    (agents, assistants, workspace default and allow-lists) to its
 *    replacement. Anything still holding the old slug (a workflow's Ask AI
 *    node) is served by the replacement through `ModelCatalogResolver`.
 *  - `retireDue`: what the scheduler runs to retire models whose date has
 *    come.
 *  - `restore`: bring a retired model back.
 *
 * It changes data and nothing else: who asked, and the audit trail, are the
 * caller's.
 */
class ModelLifecycle
{
    public function __construct(private readonly ModelCatalogResolver $resolver) {}

    /**
     * @throws ModelLifecycleException
     */
    public function deprecate(ModelCatalog $model, string $replacementSlug, CarbonInterface $retiresAt): ModelCatalog
    {
        $replacement = $this->replacementFor($model, $replacementSlug);

        if ($retiresAt->isPast()) {
            throw new ModelLifecycleException('The retirement date must be in the future. To retire the model now, retire it instead.');
        }

        $model->forceFill(['retires_at' => $retiresAt, 'replacement_slug' => $replacement->slug])->save();

        $this->resolver->forgetAll($model);

        return $model;
    }

    /**
     * @return array{replacement: ?string, agents: int, assistants: int, workspaces: int}
     *
     * @throws ModelLifecycleException
     */
    public function retire(ModelCatalog $model, ?string $replacementSlug = null): array
    {
        $slug = $replacementSlug ?? $model->replacement_slug;
        $replacement = $slug === null ? null : $this->replacementFor($model, $slug);

        $users = $this->usage($model);

        if ($replacement === null && ($users['agents'] + $users['assistants'] + $users['workspaces'] > 0 || $model->is_internal)) {
            throw new ModelLifecycleException($model->is_internal
                ? 'An internal model is used by the platform itself, so it needs a replacement.'
                : "{$users['agents']} agent(s), {$users['assistants']} assistant(s) and {$users['workspaces']} workspace setting(s) use this model. Choose a replacement to move them to.");
        }

        DB::transaction(function () use ($model, $replacement): void {
            if ($replacement !== null) {
                $this->moveUsers($model, $replacement);
            }

            $model->forceFill([
                'is_active' => false,
                'retires_at' => $model->retires_at !== null && $model->retires_at->isPast() ? $model->retires_at : now(),
                'replacement_slug' => $replacement?->slug,
            ])->save();
        });

        $this->resolver->forgetAll($model);

        return ['replacement' => $replacement?->slug, ...$users];
    }

    /**
     * Brings a retired model back into service. It needs an enabled route:
     * restoring one that cannot run would only move the failure.
     *
     * @throws ModelLifecycleException
     */
    public function restore(ModelCatalog $model): ModelCatalog
    {
        if (! $model->routes()->where('is_enabled', true)->exists()) {
            throw new ModelLifecycleException('This model has no enabled route, so it cannot be restored. Enable a route first.');
        }

        $model->forceFill(['is_active' => true, 'retires_at' => null, 'replacement_slug' => null])->save();

        $this->resolver->forgetAll($model);

        return $model;
    }

    /**
     * Retires every active model whose date has come and which names a
     * replacement. One that fails is reported and left alone, so a single
     * bad entry cannot stop the rest.
     *
     * @return array{retired: array<string, array{replacement: ?string, agents: int, assistants: int, workspaces: int}>, failed: array<string, string>}
     */
    public function retireDue(): array
    {
        $result = ['retired' => [], 'failed' => []];

        ModelCatalog::query()
            ->where('is_active', true)
            ->whereNotNull('replacement_slug')
            ->whereNotNull('retires_at')
            ->where('retires_at', '<=', now())
            ->orderBy('slug')
            ->get()
            ->each(function (ModelCatalog $model) use (&$result): void {
                try {
                    $result['retired'][$model->slug] = $this->retire($model);
                } catch (ModelLifecycleException $e) {
                    $result['failed'][$model->slug] = $e->getMessage();
                }
            });

        return $result;
    }

    /**
     * What a model's retirement would move.
     *
     * @return array{agents: int, assistants: int, workspaces: int}
     */
    public function usage(ModelCatalog $model): array
    {
        return [
            'agents' => Agent::query()->where('model_catalog_id', $model->id)->count(),
            'assistants' => Assistant::query()->where('model_catalog_id', $model->id)->count(),
            'workspaces' => WorkspaceModelSettings::query()->get()->filter(
                fn (WorkspaceModelSettings $settings): bool => $settings->default_model_catalog_id === $model->id
                    || in_array($model->id, (array) $settings->allowed_model_catalog_ids, true),
            )->count(),
        ];
    }

    /**
     * The entry that may stand in for `$model`: it must exist, be in service,
     * and be the same kind (an internal model is only replaced by an internal
     * one, a public one by a public one).
     *
     * @throws ModelLifecycleException
     */
    public function replacementFor(ModelCatalog $model, string $slug): ModelCatalog
    {
        if ($slug === $model->slug) {
            throw new ModelLifecycleException('A model cannot replace itself.');
        }

        $replacement = ModelCatalog::query()->where('slug', $slug)->first();

        if ($replacement === null) {
            throw new ModelLifecycleException("There is no model [{$slug}] to replace it with.");
        }

        if ($replacement->isRetired()) {
            throw new ModelLifecycleException("[{$slug}] is itself retired, so it cannot be a replacement.");
        }

        if ((bool) $replacement->is_internal !== (bool) $model->is_internal) {
            throw new ModelLifecycleException('An internal model can only be replaced by an internal one, and a public model by a public one.');
        }

        return $replacement;
    }

    /**
     * Each record is saved as a model, not updated in bulk, so the agent
     * history and eval hooks run for the model change they are.
     */
    private function moveUsers(ModelCatalog $from, ModelCatalog $to): void
    {
        Agent::query()->where('model_catalog_id', $from->id)->get()
            ->each(fn (Agent $agent) => $agent->update(['model_catalog_id' => $to->id]));

        Assistant::query()->where('model_catalog_id', $from->id)->get()
            ->each(fn (Assistant $assistant) => $assistant->update(['model_catalog_id' => $to->id]));

        WorkspaceModelSettings::query()->get()->each(function (WorkspaceModelSettings $settings) use ($from, $to): void {
            $allowed = $settings->allowed_model_catalog_ids;
            $changes = [];

            if ($settings->default_model_catalog_id === $from->id) {
                $changes['default_model_catalog_id'] = $to->id;
            }

            if (is_array($allowed) && in_array($from->id, $allowed, true)) {
                $changes['allowed_model_catalog_ids'] = array_values(array_unique(array_map(
                    fn (string $id): string => $id === $from->id ? $to->id : $id,
                    $allowed,
                )));
            }

            if ($changes !== []) {
                $settings->update($changes);
            }
        });
    }
}
