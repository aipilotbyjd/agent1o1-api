<?php

namespace App\Services\Triggers\Push;

use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Pipedrive triggers through its webhooks.
 *
 * One webhook per object and action (a deal added, a person updated...), shared by
 * the triggers that watch it. Pipedrive doesn't sign deliveries, so the URL carries
 * a random token (kept hashed); a delivery only makes the trigger's own poll look
 * now. Creating a webhook needs a Pipedrive user who may manage them; if not, the
 * trigger keeps polling.
 */
class PipedriveSource extends AbstractTokenWatchedSource
{
    private const string API = 'https://api.pipedrive.com/v1';

    /**
     * Each reader node's webhook object and action.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const array EVENTS = [
        'pipedrive_list_new_deals' => ['deal', 'added'],
        'pipedrive_list_changed_deals' => ['deal', 'updated'],
        'pipedrive_list_new_persons' => ['person', 'added'],
        'pipedrive_list_changed_persons' => ['person', 'updated'],
        'pipedrive_list_new_organizations' => ['organization', 'added'],
        'pipedrive_list_changed_organizations' => ['organization', 'updated'],
        'pipedrive_list_new_activities' => ['activity', 'added'],
        'pipedrive_list_changed_activities' => ['activity', 'updated'],
    ];

    public function provider(): string
    {
        return 'pipedrive';
    }

    protected function nodeTypes(): array
    {
        return array_keys(self::EVENTS);
    }

    public function watchKeyFor(Trigger $trigger): ?string
    {
        $event = self::EVENTS[$trigger->config['node_type'] ?? ''] ?? null;

        return $event === null ? null : "{$event[0]}.{$event[1]}";
    }

    public function createWatch(ProviderWatch $watch, Trigger $trigger): void
    {
        $this->register($watch);
    }

    /**
     * Pipedrive switches a webhook off after repeated failures, or someone removed it: make it again if so.
     */
    public function renewWatch(ProviderWatch $watch): void
    {
        $hooks = (array) $this->ok($this->pipedriveHttp($this->credentialOf($watch))->get(self::API.'/webhooks'))->json('data', []);
        $found = collect($hooks)->first(fn (mixed $hook): bool => is_array($hook) && (string) ($hook['id'] ?? '') === (string) $watch->external_id);

        if ($found === null || ($found['is_active'] ?? true) === false) {
            $this->register($watch);

            return;
        }

        $watch->renew_at = now()->addDay();
    }

    public function deleteWatch(ProviderWatch $watch): void
    {
        $response = $this->pipedriveHttp($this->credentialOf($watch))->delete(self::API.'/webhooks/'.rawurlencode((string) $watch->external_id));

        if ($response->status() !== 404) {
            $this->ok($response);
        }
    }

    /**
     * Make the webhook for the watch's object and action, with a fresh token URL.
     */
    private function register(ProviderWatch $watch): void
    {
        [$object, $action] = explode('.', $watch->resource_key);
        $http = $this->pipedriveHttp($this->credentialOf($watch));
        $old = $watch->external_id;

        $this->registerUrl($watch, function (string $url) use ($watch, $http, $object, $action): void {
            $response = $this->ok($http->post(self::API.'/webhooks', ['subscription_url' => $url, 'event_action' => $action, 'event_object' => $object]));
            $watch->external_id = (string) $response->json('data.id');
        });

        // The old webhook, if there was one, points at a token that no longer exists.
        if ($old !== null && $old !== '' && $old !== $watch->external_id) {
            $http->delete(self::API.'/webhooks/'.rawurlencode((string) $old));
        }

        $watch->expires_at = null;
        $watch->renew_at = now()->addDay();
    }

    private function pipedriveHttp(ConnectorCredential $credential): PendingRequest
    {
        return Http::withHeaders(['x-api-token' => $this->tokens->accessToken($credential)])->acceptJson()->timeout(30);
    }
}
