<?php

namespace App\Services\Connectors;

use App\Enums\Connectors\ConnectorCredentialScope;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Turns a node's `config` into a usable access token for one connector,
 * scoped to a workspace and the user acting in it. A `ConnectorCredential`
 * is always looked up by `workspace_id` first (a caller can never reach a
 * credential belonging to another workspace by guessing its id) with a
 * fallback to a plain `access_token` config field for backward
 * compatibility with nodes configured before `ConnectorCredential` existed
 * (docs/PLAN.md Phase 6).
 *
 * With neither pinned, falls back to a *default* credential: Gumloop's
 * "Personal Default"/"if you only have one account connected, it's
 * automatically your default" (see
 * docs/gumloop/output/raw/core-concepts/credentials.md). The acting user's
 * personal default takes priority over the workspace's team default,
 * matching the doc's "everyone uses their own account by default" rule of
 * thumb.
 *
 * Shared by node execution (`ResolvesConnectorCredential`, acting user =
 * `Run::triggered_by`) and the editor's dynamic field options
 * (`NodeOptionsService`, acting user = the member editing the node).
 */
class ConnectorCredentialResolver
{
    public function __construct(private readonly ConnectorTokens $tokens) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public function accessToken(string $connectorKey, string $workspaceId, ?string $userId, array $config, bool $allowRawToken = true): string
    {
        $credentialId = $config['credential_id'] ?? null;

        if ($credentialId !== null && $credentialId !== '') {
            return $this->tokens->accessToken($this->find($connectorKey, $workspaceId, $userId, (string) $credentialId));
        }

        $token = $config['access_token'] ?? null;

        if ($allowRawToken && is_string($token) && $token !== '') {
            return $token;
        }

        return $this->tokens->accessToken($this->default($connectorKey, $workspaceId, $userId));
    }

    /**
     * The stored fields of the credential a config resolves to, for the apps
     * whose login is more than one token (a Twilio account and its token, a
     * mail server): the same lookup as `accessToken`, minus the raw-token
     * shortcut.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function credentialData(string $connectorKey, string $workspaceId, ?string $userId, array $config, bool $markUsed = true): array
    {
        $credentialId = $config['credential_id'] ?? null;

        $credential = $credentialId !== null && $credentialId !== ''
            ? $this->find($connectorKey, $workspaceId, $userId, (string) $credentialId)
            : $this->default($connectorKey, $workspaceId, $userId);

        if ($markUsed) {
            $credential->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        return (array) $credential->data;
    }

    /**
     * A pinned credential must still be one the caller may use: the
     * workspace's, for this connector, and (when personal) created by the
     * acting user. Anything else reads as "not found" rather than
     * confirming the id exists.
     */
    public function find(string $connectorKey, string $workspaceId, ?string $userId, string $credentialId): ConnectorCredential
    {
        $connector = Connector::where('key', $connectorKey)->first();

        $credential = $connector === null ? null : ConnectorCredential::query()
            ->where('workspace_id', $workspaceId)
            ->where('connector_id', $connector->id)
            ->where(fn ($usable) => $usable
                ->where('scope', ConnectorCredentialScope::Team->value)
                ->when($userId !== null, fn ($query) => $query->orWhere(fn ($personal) => $personal
                    ->where('scope', ConnectorCredentialScope::Personal->value)
                    ->where('created_by', $userId))))
            ->find($credentialId);

        if ($credential === null) {
            throw new RuntimeException("Connector credential [{$credentialId}] not found in this workspace.");
        }

        return $credential;
    }

    /**
     * The credential used when nothing pins one: the acting user's personal
     * default (or their sole personal credential, if they have exactly one
     * and none is marked default) for this connector, else the workspace's
     * team default (or its sole team credential).
     */
    public function default(string $connectorKey, string $workspaceId, ?string $userId): ConnectorCredential
    {
        $connector = Connector::where('key', $connectorKey)->first();
        $credential = null;

        if ($connector !== null && $userId !== null) {
            $credential = $this->preferredOf(
                ConnectorCredential::query()
                    ->where('workspace_id', $workspaceId)
                    ->where('connector_id', $connector->id)
                    ->where('scope', ConnectorCredentialScope::Personal->value)
                    ->where('created_by', $userId)
                    ->get(),
            );
        }

        $credential ??= $connector === null ? null : $this->preferredOf(
            ConnectorCredential::query()
                ->where('workspace_id', $workspaceId)
                ->where('connector_id', $connector->id)
                ->where('scope', ConnectorCredentialScope::Team->value)
                ->get(),
        );

        if ($credential === null) {
            throw new RuntimeException(ucfirst($connectorKey).' access_token or credential_id is required.');
        }

        return $credential;
    }

    /**
     * @param  Collection<int, ConnectorCredential>  $candidates
     */
    private function preferredOf(Collection $candidates): ?ConnectorCredential
    {
        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates->firstWhere('is_default', true) ?? ($candidates->count() === 1 ? $candidates->first() : null);
    }
}
