<?php

namespace App\Nodes\Integrations\Concerns;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use App\Services\Connectors\ConnectorCredentialResolver;

/**
 * Shared by every integration node to turn a step's `config` into a usable
 * access token, tenant-scoped by the executing `Run`: see
 * `ConnectorCredentialResolver` for the lookup and default rules. A node's
 * `category()` doubles as its connector key.
 */
trait ResolvesConnectorCredential
{
    /**
     * @param  array<string, mixed>  $config
     */
    protected function resolveAccessToken(Run $run, array $config): string
    {
        return app(ConnectorCredentialResolver::class)->accessToken(
            $this->category(),
            $run->workspace_id,
            $run->triggered_by,
            $config,
        );
    }

    /**
     * Every stored field of the step's account, for apps whose login is more
     * than a token.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function resolveCredentialData(Run $run, array $config): array
    {
        return app(ConnectorCredentialResolver::class)->credentialData(
            $this->category(),
            $run->workspace_id,
            $run->triggered_by,
            $config,
        );
    }

    /**
     * The account picker every integration node's `configSchema()` starts
     * with, plus the legacy raw `access_token` field (hidden from the
     * editor, still honoured at run time).
     *
     * @return array<string, array<string, mixed>>
     */
    protected function credentialFields(): array
    {
        return [
            'credential_id' => Field::credential($this->category()),
            'access_token' => Field::hidden('Legacy raw access token. Prefer connecting an account instead.'),
        ];
    }
}
