<?php

namespace App\Services\Triggers\Push;

use App\Contracts\Triggers\WatchedTriggerSource;
use App\Exceptions\ConnectorException;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use App\Services\Connectors\ConnectorTokens;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * What every watched source shares: being on when its config says so, serving
 * the reader nodes it is named for, and calling the provider as the trigger's
 * connected account.
 */
abstract class AbstractWatchedSource implements WatchedTriggerSource
{
    public function __construct(protected readonly ConnectorTokens $tokens) {}

    /**
     * The reader node types this source can watch for.
     *
     * @return list<string>
     */
    abstract protected function nodeTypes(): array;

    /**
     * Whether the provider-side settings it needs are there (a topic, a token).
     */
    protected function isConfigured(): bool
    {
        return true;
    }

    /**
     * On when its switch is on and it is configured.
     */
    public function isEnabled(): bool
    {
        return (bool) config("triggers.push.{$this->provider()}.enabled") && $this->isConfigured();
    }

    /**
     * A reader node it watches for, reading through a connected account.
     */
    public function supports(Trigger $trigger): bool
    {
        return in_array($trigger->config['node_type'] ?? null, $this->nodeTypes(), true) && $trigger->credential_id !== null;
    }

    /**
     * Nothing to warn about unless a source knows better.
     */
    public function accessWarning(Trigger $trigger): ?string
    {
        return null;
    }

    /**
     * The node settings the trigger polls with.
     *
     * @return array<string, mixed>
     */
    protected function nodeConfig(Trigger $trigger): array
    {
        return (array) ($trigger->config['node_config'] ?? []);
    }

    /**
     * The account a watch was made with.
     *
     * @throws ConnectorException when it is gone
     */
    protected function credentialOf(ProviderWatch $watch): ConnectorCredential
    {
        return ConnectorCredential::query()
            ->where('workspace_id', $watch->workspace_id)
            ->find($watch->credential_id)
            ?? throw new ConnectorException('The connected account for this trigger is gone. Reconnect it.');
    }

    /**
     * A request signed with the account's token.
     */
    protected function http(ConnectorCredential $credential): PendingRequest
    {
        return Http::withToken($this->tokens->accessToken($credential))->acceptJson()->timeout(20);
    }

    /**
     * Turn an error answer into an exception: a refused token means the account
     * needs reconnecting, anything else is thrown for the caller to judge.
     *
     * @throws ConnectorException on 401
     */
    protected function ok(Response $response): Response
    {
        if ($response->status() === 401) {
            throw new ConnectorException('The provider refused the connected account. Reconnect it.');
        }

        return $response->throw();
    }

    /**
     * Where the provider sends notifications for this source.
     */
    protected function callbackUrl(): string
    {
        return url('/api/hooks/apps/'.$this->provider());
    }

    /**
     * A fresh secret for a watch.
     */
    protected function newSecret(): string
    {
        return bin2hex(random_bytes(24));
    }
}
