<?php

namespace App\Services\Triggers\Push;

use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Asana triggers through a webhook on the project.
 *
 * One webhook per project, shared by the project's triggers. Asana proves a new
 * target by posting a secret to it and waiting for it back, so the watch is saved
 * first and the token URL (kept hashed) answers that handshake. Later deliveries
 * are signed with that secret; here a delivery only makes the trigger's own poll
 * look now, so the token is the check.
 */
class AsanaSource extends AbstractTokenWatchedSource
{
    private const string API = 'https://app.asana.com/api/1.0';

    public function provider(): string
    {
        return 'asana';
    }

    protected function nodeTypes(): array
    {
        return ['asana_list_new_tasks', 'asana_list_completed_tasks', 'asana_list_updated_tasks'];
    }

    /**
     * A trigger that names its project.
     */
    public function supports(Trigger $trigger): bool
    {
        return parent::supports($trigger) && $this->watchKeyFor($trigger) !== null;
    }

    public function watchKeyFor(Trigger $trigger): ?string
    {
        $project = trim((string) ($this->nodeConfig($trigger)['project_id'] ?? ''));

        return preg_match('/^\d+$/', $project) === 1 ? $project : null;
    }

    public function createWatch(ProviderWatch $watch, Trigger $trigger): void
    {
        $this->register($watch);
    }

    /**
     * Asana switches a webhook off after long failures, or someone removed it: make it again if so.
     */
    public function renewWatch(ProviderWatch $watch): void
    {
        $response = $this->asanaHttp($this->credentialOf($watch))->get(self::API.'/webhooks/'.rawurlencode((string) $watch->external_id), ['opt_fields' => 'active']);

        if ($response->status() === 404 || ($response->successful() && $response->json('data.active') === false)) {
            $this->register($watch);

            return;
        }

        $this->ok($response);

        $watch->renew_at = now()->addDay();
    }

    public function deleteWatch(ProviderWatch $watch): void
    {
        $response = $this->asanaHttp($this->credentialOf($watch))->delete(self::API.'/webhooks/'.rawurlencode((string) $watch->external_id));

        if ($response->status() !== 404) {
            $this->ok($response);
        }
    }

    /**
     * Make the project's webhook, with a fresh token URL.
     */
    private function register(ProviderWatch $watch): void
    {
        $http = $this->asanaHttp($this->credentialOf($watch));
        $old = $watch->external_id;

        $this->registerUrl($watch, function (string $url) use ($watch, $http): void {
            $response = $this->ok($http->post(self::API.'/webhooks', ['data' => ['resource' => $watch->resource_key, 'target' => $url]]));
            $watch->external_id = (string) $response->json('data.gid');
        });

        // The old webhook, if there was one, points at a token that no longer exists.
        if ($old !== null && $old !== '' && $old !== $watch->external_id) {
            $http->delete(self::API.'/webhooks/'.rawurlencode((string) $old));
        }

        $watch->expires_at = null;
        $watch->renew_at = now()->addDay();
    }

    private function asanaHttp(ConnectorCredential $credential): PendingRequest
    {
        return Http::withToken($this->tokens->accessToken($credential))->acceptJson()->timeout(30);
    }
}
