<?php

namespace App\Services\Ai;

use App\Enums\Ai\PlatformKeyUsage;
use App\Enums\Connectors\ConnectorCredentialScope;
use App\Models\Agents\Agent;
use App\Models\Ai\ModelCatalog;
use App\Models\Ai\WorkspaceAiKeyPolicy;
use App\Models\Workspaces\Workspace;
use Illuminate\Support\Collection;

/**
 * What changing a workspace's `WorkspaceAiKeyPolicy` would do, so the
 * settings screen can say so before an admin commits to it: the catalog
 * models that would stop being usable, the agents running on them, the
 * models that would lose the platform's key as their backup, and the
 * personal keys that would stop being used.
 *
 * Judged on the workspace's team keys (what every member can count on)
 * since a personal key only ever covers its owner.
 */
class AiKeyPolicyImpact
{
    public function __construct(private readonly ByokProviderRegistrar $byok) {}

    /**
     * @return array{
     *     unavailable_models: array<int, array{id: string, display_name: string}>,
     *     affected_agents: array<int, array{id: string, name: string, model: string}>,
     *     models_losing_backup: array<int, array{id: string, display_name: string}>,
     *     ignored_personal_keys: int,
     * }
     */
    public function preview(Workspace $workspace, PlatformKeyUsage $usage, bool $allowPersonalKeys): array
    {
        $current = WorkspaceAiKeyPolicy::forWorkspace($workspace->id);
        $ownKeyProviders = $this->byok->coveredProviders($workspace->id, null);

        $unavailable = collect();
        $losingBackup = collect();

        foreach ($this->catalog() as $entry) {
            $providers = $entry->routes->pluck('execution_provider');
            $covered = $providers->intersect($ownKeyProviders)->isNotEmpty();
            $platform = $providers->contains(fn (string $provider): bool => ModelCatalogResolver::providerIsConfigured($provider));

            $availableNow = $covered || ($platform && $current->platform_usage !== PlatformKeyUsage::Never);
            $availableAfter = $covered || ($platform && $usage !== PlatformKeyUsage::Never);

            if ($availableNow && ! $availableAfter) {
                $unavailable->push($entry);
            }

            $backedNow = $covered && $platform && $current->platform_usage === PlatformKeyUsage::Fallback;
            $backedAfter = $covered && $platform && $usage === PlatformKeyUsage::Fallback;

            if ($backedNow && ! $backedAfter) {
                $losingBackup->push($entry);
            }
        }

        return [
            'unavailable_models' => $this->summaries($unavailable),
            'affected_agents' => $this->agentsOn($workspace, $unavailable),
            'models_losing_backup' => $this->summaries($losingBackup),
            'ignored_personal_keys' => $current->allow_personal_keys && ! $allowPersonalKeys
                ? $workspace->aiProviderCredentials()->where('scope', ConnectorCredentialScope::Personal->value)->count()
                : 0,
        ];
    }

    /**
     * @return Collection<int, ModelCatalog>
     */
    private function catalog(): Collection
    {
        return ModelCatalog::query()
            ->where('is_active', true)
            ->where('is_internal', false)
            ->with(['routes' => fn ($query) => $query->where('is_enabled', true)])
            ->orderBy('sort_order')
            ->orderBy('display_name')
            ->get()
            ->filter(fn (ModelCatalog $entry): bool => $entry->routes->isNotEmpty());
    }

    /**
     * @param  Collection<int, ModelCatalog>  $entries
     * @return array<int, array{id: string, display_name: string}>
     */
    private function summaries(Collection $entries): array
    {
        return $entries->map(fn (ModelCatalog $entry): array => ['id' => $entry->id, 'display_name' => $entry->display_name])->values()->all();
    }

    /**
     * @param  Collection<int, ModelCatalog>  $entries
     * @return array<int, array{id: string, name: string, model: string}>
     */
    private function agentsOn(Workspace $workspace, Collection $entries): array
    {
        if ($entries->isEmpty()) {
            return [];
        }

        $names = $entries->pluck('display_name', 'id');

        return Agent::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('model_catalog_id', $names->keys())
            ->orderBy('name')
            ->get(['id', 'name', 'model_catalog_id'])
            ->map(fn (Agent $agent): array => ['id' => $agent->id, 'name' => $agent->name, 'model' => $names[$agent->model_catalog_id]])
            ->all();
    }
}
