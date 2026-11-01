<?php

namespace App\Services\Triggers\Push;

use App\Exceptions\WatchRefusedException;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use App\Services\Connectors\CrmAccessTokens;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use stdClass;

/**
 * Zoho Desk triggers through its webhooks.
 *
 * One webhook per kind of event (a ticket added, updated, or a thread added),
 * shared by the triggers that watch it. Desk's deliveries are not signed, so the
 * URL carries a random token (kept hashed); a delivery only makes the trigger's
 * own poll look now. Creating a webhook needs a Desk admin; if not, the trigger
 * keeps polling.
 */
class ZohoDeskSource extends AbstractTokenWatchedSource
{
    /**
     * Each reader node's Desk event.
     *
     * @var array<string, string>
     */
    private const array EVENTS = [
        'zoho_desk_list_new_tickets' => 'Ticket_Add',
        'zoho_desk_list_changed_tickets' => 'Ticket_Update',
        'zoho_desk_list_customer_replies' => 'Ticket_Thread_Add',
    ];

    public function provider(): string
    {
        return 'zoho_desk';
    }

    protected function nodeTypes(): array
    {
        return array_keys(self::EVENTS);
    }

    public function watchKeyFor(Trigger $trigger): ?string
    {
        return self::EVENTS[$trigger->config['node_type'] ?? ''] ?? null;
    }

    public function createWatch(ProviderWatch $watch, Trigger $trigger): void
    {
        $this->register($watch);
    }

    /**
     * Desk switches a webhook off after repeated failures, or someone removed it: make it again if so.
     */
    public function renewWatch(ProviderWatch $watch): void
    {
        [$http, $base] = $this->client($this->credentialOf($watch));
        $response = $http->get("{$base}/webhooks/".rawurlencode((string) $watch->external_id));

        if ($response->status() === 404 || ($response->successful() && $response->json('isEnabled') === false)) {
            $this->register($watch);

            return;
        }

        $this->ok($response);
        $watch->renew_at = now()->addDay();
    }

    public function deleteWatch(ProviderWatch $watch): void
    {
        [$http, $base] = $this->client($this->credentialOf($watch));
        $response = $http->delete("{$base}/webhooks/".rawurlencode((string) $watch->external_id));

        if ($response->status() !== 404) {
            $this->ok($response);
        }
    }

    /**
     * Make the webhook for the watch's event, with a fresh token URL.
     */
    private function register(ProviderWatch $watch): void
    {
        [$http, $base] = $this->client($this->credentialOf($watch));
        $old = $watch->external_id;

        $this->registerUrl($watch, function (string $url) use ($watch, $http, $base): void {
            $response = $this->ok($http->post("{$base}/webhooks", ['name' => 'Workflows '.$watch->resource_key, 'url' => $url, 'subscriptions' => [$watch->resource_key => new stdClass]]));

            if (! filled($response->json('id'))) {
                throw new WatchRefusedException('Zoho Desk did not create the webhook.');
            }

            $watch->external_id = (string) $response->json('id');
        });

        // The old webhook, if there was one, points at a token that no longer exists.
        if ($old !== null && $old !== '' && $old !== $watch->external_id) {
            $http->delete("{$base}/webhooks/".rawurlencode((string) $old));
        }

        $watch->expires_at = null;
        $watch->renew_at = now()->addDay();
    }

    /**
     * An authorised client for the account, and the API root of its data center.
     *
     * @return array{0: PendingRequest, 1: string}
     *
     * @throws WatchRefusedException when the connection lacks the organization id
     */
    private function client(ConnectorCredential $credential): array
    {
        $data = (array) $credential->data;
        $org = trim((string) ($data['org_id'] ?? ''));

        if (preg_match('/^\d+$/', $org) !== 1) {
            throw new WatchRefusedException('Add the Zoho Desk organization id to the connection.');
        }

        try {
            $login = app(CrmAccessTokens::class)->zohoApp($data, 'desk');
        } catch (InvalidArgumentException $exception) {
            throw new WatchRefusedException($exception->getMessage());
        }

        return [Http::withHeaders(['Authorization' => 'Zoho-oauthtoken '.$login['token'], 'orgId' => $org])->acceptJson()->timeout(30), $login['base']];
    }
}
