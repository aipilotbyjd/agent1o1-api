<?php

namespace App\Ai\Tools\WorkflowBuilder;

use App\Enums\Connectors\ConnectorCredentialScope;
use App\Models\Connectors\ConnectorCredential;
use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The connected accounts a node's `credential_id` can name: the workspace's
 * team accounts and the acting member's own personal ones, the same set
 * `ConnectorCredentialResolver::find()` accepts at run time.
 */
class ListConnectedAccountsTool implements Tool
{
    public function __construct(
        public readonly WorkflowBuilderSession $session,
        public readonly ?User $actingUser = null,
    ) {}

    public function name(): string
    {
        return 'list_connected_accounts';
    }

    public function description(): Stringable|string
    {
        return "List the accounts connected in this workspace (id, name, connector, whether it is the default and whether it works right now). A node for an app takes one as its config \"credential_id\"; match the account's connector to the node's category from list_available_nodes.";
    }

    public function handle(Request $request): Stringable|string
    {
        $accounts = ConnectorCredential::query()
            ->with('connector')
            ->where('workspace_id', $this->session->workspace_id)
            ->where(fn ($query) => $query
                ->where('scope', ConnectorCredentialScope::Team->value)
                ->when($this->actingUser !== null, fn ($query) => $query->orWhere('created_by', $this->actingUser->id)))
            ->orderBy('name')
            ->get();

        if ($accounts->isEmpty()) {
            return 'No accounts are connected in this workspace yet. Leave credential_id empty and tell the user which app to connect.';
        }

        return json_encode($accounts->map(fn (ConnectorCredential $credential): array => [
            'id' => $credential->id,
            'name' => $credential->name,
            'connector' => $credential->connector?->key,
            'connector_name' => $credential->connector?->name,
            'scope' => $credential->scope->value,
            'is_default' => $credential->is_default,
            'usable' => $credential->isUsable(),
        ])->all(), JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
