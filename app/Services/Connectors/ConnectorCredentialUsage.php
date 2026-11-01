<?php

namespace App\Services\Connectors;

use App\Enums\Connectors\ConnectorCredentialScope;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentToolBinding;
use App\Models\Agents\KnowledgeSource;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowNode;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * What would break if a connection went away. A workflow node or agent tool
 * uses a credential either by pinning its `credential_id`, or (with none
 * pinned) by falling back to it as the default account, following the same
 * rules as `ConnectorCredentialResolver::default()`.
 */
class ConnectorCredentialUsage
{
    public function __construct(private readonly NodeRegistry $registry) {}

    /**
     * @return array{
     *     is_default_for_unpinned: bool,
     *     workflows: array<int, array{id: string, name: string, nodes_count: int, via: 'pinned'|'default'}>,
     *     agents: array<int, array{id: string, name: string, tools_count: int, via: 'pinned'|'default'}>,
     *     knowledge_sources: array<int, array{id: string, name: string}>,
     *     total: int,
     * }
     */
    public function for(ConnectorCredential $credential): array
    {
        $credential->loadMissing('connector');
        $isDefault = $this->isEffectiveDefault($credential);
        $nodeTypes = $isDefault ? $this->nodeTypesFor($credential->connector?->key) : [];

        $workflows = $this->workflows($credential, $nodeTypes);
        $agents = $this->agents($credential, $nodeTypes);
        $knowledgeSources = KnowledgeSource::query()
            ->where('workspace_id', $credential->workspace_id)
            ->where('connector_credential_id', $credential->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (KnowledgeSource $source): array => ['id' => $source->id, 'name' => $source->name])
            ->all();

        return [
            'is_default_for_unpinned' => $isDefault,
            'workflows' => $workflows,
            'agents' => $agents,
            'knowledge_sources' => $knowledgeSources,
            'total' => count($workflows) + count($agents) + count($knowledgeSources),
        ];
    }

    /**
     * @param  array<int, string>  $nodeTypes
     * @return array<int, array{id: string, name: string, nodes_count: int, via: 'pinned'|'default'}>
     */
    private function workflows(ConnectorCredential $credential, array $nodeTypes): array
    {
        $nodes = WorkflowNode::query()
            ->whereHas('workflow', fn (Builder $workflow) => $workflow->where('workspace_id', $credential->workspace_id))
            ->where(fn (Builder $query) => $this->usesCredential($query, $credential, $nodeTypes, 'type'))
            ->get(['id', 'workflow_id', 'type', 'config']);

        return $this->summarise(
            $nodes,
            'workflow_id',
            fn (Collection $ids) => Workflow::query()->whereIn('id', $ids)->pluck('name', 'id'),
            $credential,
            'nodes_count',
        );
    }

    /**
     * @param  array<int, string>  $nodeTypes
     * @return array<int, array{id: string, name: string, tools_count: int, via: 'pinned'|'default'}>
     */
    private function agents(ConnectorCredential $credential, array $nodeTypes): array
    {
        $bindings = AgentToolBinding::query()
            ->whereHas('agent', fn (Builder $agent) => $agent->where('workspace_id', $credential->workspace_id))
            ->where(fn (Builder $query) => $this->usesCredential($query, $credential, $nodeTypes, 'node_type'))
            ->get(['id', 'agent_id', 'node_type', 'config']);

        return $this->summarise(
            $bindings,
            'agent_id',
            fn (Collection $ids) => Agent::query()->whereIn('id', $ids)->pluck('name', 'id'),
            $credential,
            'tools_count',
        );
    }

    /**
     * @param  Builder<WorkflowNode>|Builder<AgentToolBinding>  $query
     * @param  array<int, string>  $nodeTypes
     */
    private function usesCredential(Builder $query, ConnectorCredential $credential, array $nodeTypes, string $typeColumn): void
    {
        $query->where('config->credential_id', $credential->id);

        if ($nodeTypes !== []) {
            $query->orWhere(fn (Builder $unpinned) => $unpinned
                ->whereIn($typeColumn, $nodeTypes)
                ->where(fn (Builder $empty) => $empty
                    ->whereNull('config->credential_id')
                    ->orWhere('config->credential_id', '')));
        }
    }

    /**
     * Groups matching rows by their parent, names each parent (soft-deleted
     * parents drop out here) and marks it `pinned` if any row pins the
     * credential outright.
     *
     * @template TKey of string
     *
     * @param  Collection<int, WorkflowNode|AgentToolBinding>  $rows
     * @param  callable(Collection<int, string>): Collection<string, string>  $names
     * @param  TKey  $countKey
     * @return array<int, array<string, mixed>>
     */
    private function summarise(Collection $rows, string $parentKey, callable $names, ConnectorCredential $credential, string $countKey): array
    {
        $grouped = $rows->groupBy($parentKey);
        $parentNames = $names($grouped->keys());

        return $grouped
            ->filter(fn (Collection $group, string $parentId): bool => $parentNames->has($parentId))
            ->map(fn (Collection $group, string $parentId): array => [
                'id' => $parentId,
                'name' => $parentNames->get($parentId),
                $countKey => $group->count(),
                'via' => $group->contains(fn ($row): bool => ($row->config['credential_id'] ?? null) === $credential->id) ? 'pinned' : 'default',
            ])
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * Mirrors `ConnectorCredentialResolver::preferredOf()`: the group's
     * marked default, or its only credential when none is marked.
     */
    public function isEffectiveDefault(ConnectorCredential $credential): bool
    {
        if ($credential->is_default) {
            return true;
        }

        $siblings = ConnectorCredential::query()
            ->where('workspace_id', $credential->workspace_id)
            ->where('connector_id', $credential->connector_id)
            ->where('scope', $credential->scope->value)
            ->when(
                $credential->scope === ConnectorCredentialScope::Personal,
                fn (Builder $query) => $query->where('created_by', $credential->created_by),
            )
            ->get(['id', 'is_default']);

        return $siblings->count() === 1 && ! $siblings->contains('is_default', true);
    }

    /**
     * @return array<int, string>
     */
    private function nodeTypesFor(?string $connectorKey): array
    {
        if ($connectorKey === null) {
            return [];
        }

        return collect($this->registry->connectors())
            ->filter(fn (string $class): bool => app($class)->category() === $connectorKey)
            ->keys()
            ->values()
            ->all();
    }
}
