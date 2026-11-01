<?php

namespace App\Services\Triggers\Push;

use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Trello board activity through a webhook on the board.
 *
 * One webhook per board, shared by the board's triggers. Trello signs
 * deliveries with the app's secret, which this app doesn't hold, so the URL
 * carries a random token (kept hashed); a delivery only makes the trigger's own
 * poll look now. Trello checks a new webhook URL with a HEAD request before it
 * accepts it, so the watch is saved first and the token URL answers that.
 */
class TrelloSource extends AbstractTokenWatchedSource
{
    private const string API = 'https://api.trello.com/1';

    public function provider(): string
    {
        return 'trello';
    }

    protected function nodeTypes(): array
    {
        return ['trello_list_activity'];
    }

    /**
     * A trigger that names its board.
     */
    public function supports(Trigger $trigger): bool
    {
        return parent::supports($trigger) && $this->watchKeyFor($trigger) !== null;
    }

    public function watchKeyFor(Trigger $trigger): ?string
    {
        $board = trim((string) ($this->nodeConfig($trigger)['board_id'] ?? ''));

        return preg_match('/^[A-Za-z0-9]+$/', $board) === 1 ? $board : null;
    }

    public function createWatch(ProviderWatch $watch, Trigger $trigger): void
    {
        $this->register($watch);
    }

    /**
     * Trello turns a webhook off after weeks of failures, or someone removed it: make it again if so.
     */
    public function renewWatch(ProviderWatch $watch): void
    {
        $response = $this->trelloHttp($this->credentialOf($watch))->get(self::API.'/webhooks/'.rawurlencode((string) $watch->external_id));

        if ($response->status() === 404 || ($response->successful() && $response->json('active') === false)) {
            $this->register($watch);

            return;
        }

        $this->ok($response);

        $watch->renew_at = now()->addDay();
    }

    public function deleteWatch(ProviderWatch $watch): void
    {
        $response = $this->trelloHttp($this->credentialOf($watch))->delete(self::API.'/webhooks/'.rawurlencode((string) $watch->external_id));

        if ($response->status() !== 404) {
            $this->ok($response);
        }
    }

    /**
     * Make the board's webhook, with a fresh token URL.
     */
    private function register(ProviderWatch $watch): void
    {
        $http = $this->trelloHttp($this->credentialOf($watch));
        $old = $watch->external_id;

        $this->registerUrl($watch, function (string $url) use ($watch, $http): void {
            $response = $this->ok($http->post(self::API.'/webhooks', ['callbackURL' => $url, 'idModel' => $watch->resource_key, 'description' => 'Flows']));
            $watch->external_id = (string) $response->json('id');
        });

        // The old webhook, if there was one, points at a token that no longer exists.
        if ($old !== null && $old !== '' && $old !== $watch->external_id) {
            $http->delete(self::API.'/webhooks/'.rawurlencode((string) $old));
        }

        $watch->expires_at = null;
        $watch->renew_at = now()->addDay();
    }

    /**
     * Trello takes the key and token in the query of every call.
     */
    private function trelloHttp(ConnectorCredential $credential): PendingRequest
    {
        return Http::withQueryParameters(['key' => (string) ($credential->data['app_key'] ?? ''), 'token' => $this->tokens->accessToken($credential)])->acceptJson()->timeout(20);
    }
}
