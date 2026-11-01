<?php

namespace App\Services\Assistant\Tools;

use App\Ai\Assistant\Tools\ConnectorNodeTool;
use App\Contracts\Assistant\ProvidesAssistantTools;
use App\Enums\Connectors\ConnectorCredentialScope;
use App\Models\Assistant\Assistant;
use App\Models\Assistant\AssistantSession;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Support\Collection;

/**
 * The owner's connected apps as assistant tools: every integration node
 * whose app the owner can use, each pinned to one account: their own
 * default for that app, else the workspace's shared one. Apps they haven't
 * connected contribute no tools.
 */
class ConnectorToolProvider implements ProvidesAssistantTools
{
    public function __construct(private readonly NodeRegistry $nodes) {}

    public function toolsFor(Assistant $assistant, AssistantSession $session): array
    {
        $credentials = $this->credentialsFor($assistant);

        return collect($this->nodes->connectors())
            ->filter(fn (string $class): bool => in_array(ResolvesConnectorCredential::class, class_uses_recursive($class), true))
            ->map(fn (string $class, string $type) => $this->nodes->resolve($type))
            ->filter(fn ($node): bool => $credentials->has($node->category()))
            ->map(fn ($node): ConnectorNodeTool => new ConnectorNodeTool($node, $assistant, $credentials->get($node->category())))
            ->values()
            ->all();
    }

    /**
     * The account the assistant uses for each app, keyed by connector key.
     *
     * @return Collection<string, ConnectorCredential>
     */
    public function credentialsFor(Assistant $assistant): Collection
    {
        $credentials = ConnectorCredential::query()
            ->with('connector')
            ->where('workspace_id', $assistant->workspace_id)
            ->where(fn ($query) => $query
                ->where('scope', ConnectorCredentialScope::Team->value)
                ->orWhere(fn ($query) => $query->where('scope', ConnectorCredentialScope::Personal->value)->where('created_by', $assistant->user_id)))
            ->get()
            ->filter(fn (ConnectorCredential $credential): bool => $credential->isUsable())
            ->groupBy('connector_id');

        return $credentials
            ->map(fn (Collection $group): ?ConnectorCredential => $this->preferred($group->where('scope', ConnectorCredentialScope::Personal))
                ?? $this->preferred($group->where('scope', ConnectorCredentialScope::Team)))
            ->filter()
            ->filter(fn (ConnectorCredential $credential): bool => $credential->connector instanceof Connector && $credential->connector->is_active)
            ->mapWithKeys(fn (ConnectorCredential $credential): array => [$credential->connector->key => $credential])
            // A plain collection: keyed by app, so `only()`/`has()` work on those keys
            // rather than on the models' primary keys as an Eloquent collection would.
            ->toBase();
    }

    /**
     * The default one, or the only one, with several and none marked
     * default, a newest-first pick keeps the choice stable.
     *
     * @param  Collection<int, ConnectorCredential>  $candidates
     */
    private function preferred(Collection $candidates): ?ConnectorCredential
    {
        return $candidates->firstWhere('is_default', true) ?? $candidates->sortByDesc('created_at')->first();
    }
}
