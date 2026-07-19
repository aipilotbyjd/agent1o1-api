<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Node;
use App\Models\Workspace;
use Illuminate\Support\Str;

/**
 * Supplies the read-only catalogs the agent builder needs: which AI providers
 * and models are available, which tool node-types can be attached, the agent
 * categories, and the supported trigger types.
 */
class AgentMetadataService
{
    /**
     * Configured AI providers (those with an API key present in config/ai.php),
     * annotated with whether the metadata catalog knows any models for them.
     *
     * @return list<array<string, mixed>>
     */
    public function providers(): array
    {
        $models = (array) config('agents.models', []);

        return collect((array) config('ai.providers', []))
            ->filter(fn (array $config) => $this->isConfigured($config))
            ->map(fn (array $config, string $name) => [
                'value' => $name,
                'label' => Str::of($name)->replace('_', ' ')->title()->toString(),
                'driver' => $config['driver'] ?? $name,
                'has_models' => ! empty($models[$name]),
            ])
            ->values()
            ->all();
    }

    /**
     * The model catalog keyed per configured provider. When $provider is given,
     * only that provider's models are returned.
     *
     * @return list<array<string, mixed>>
     */
    public function models(?string $provider = null): array
    {
        $catalog = (array) config('agents.models', []);
        $configured = collect((array) config('ai.providers', []))
            ->filter(fn (array $config) => $this->isConfigured($config))
            ->keys();

        return collect($catalog)
            ->only($provider ? [$provider] : $configured->all())
            ->map(fn (array $models, string $name) => [
                'provider' => $name,
                'models' => $models,
            ])
            ->values()
            ->all();
    }

    /**
     * Tool node-types an agent may be configured with — the active entries from
     * the global node library plus any custom nodes owned by the workspace.
     *
     * @return list<array<string, mixed>>
     */
    public function tools(Workspace $workspace): array
    {
        return Node::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('workspace_id')->orWhere('workspace_id', $workspace->id))
            ->orderBy('name')
            ->get(['type', 'name', 'description', 'icon', 'color', 'credential_type', 'input_schema', 'is_premium'])
            ->map(fn (Node $node) => [
                'node_type' => $node->type,
                'name' => $node->name,
                'description' => $node->description,
                'icon' => $node->icon,
                'color' => $node->color,
                'credential_type' => $node->credential_type,
                'input_schema' => $node->input_schema,
                'is_premium' => $node->is_premium,
            ])
            ->values()
            ->all();
    }

    /**
     * Curated agent categories merged with any distinct categories already in
     * use across the workspace's agents.
     *
     * @return list<array<string, string>>
     */
    public function categories(Workspace $workspace): array
    {
        $curated = collect((array) config('agents.categories', []));

        $used = $workspace->agents()
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->reject(fn ($category) => $curated->contains('value', $category))
            ->map(fn ($category) => ['value' => $category, 'label' => Str::of($category)->replace('_', ' ')->title()->toString()]);

        return $curated->merge($used)->values()->all();
    }

    /**
     * The supported trigger types and their config schemas.
     *
     * @return list<array<string, mixed>>
     */
    public function triggerTypes(): array
    {
        return (array) config('agents.trigger_types', []);
    }

    /**
     * A provider is usable once it has a non-empty API key (or is a keyless
     * local driver such as Ollama).
     *
     * @param  array<string, mixed>  $config
     */
    private function isConfigured(array $config): bool
    {
        if (($config['driver'] ?? null) === 'ollama') {
            return true;
        }

        return ! empty($config['key']);
    }
}
