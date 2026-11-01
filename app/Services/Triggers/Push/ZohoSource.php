<?php

namespace App\Services\Triggers\Push;

use App\Exceptions\WatchRefusedException;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use App\Services\Connectors\CrmAccessTokens;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Zoho CRM triggers through its notification channels.
 *
 * A channel watches one module for one kind of change (a lead created, a deal
 * edited), shared by the triggers that watch it. Zoho ends a channel after at most
 * a day, so the check renews it every twelve hours. A delivery carries the token
 * given at creation, but only makes the trigger's own poll look now, so the URL's
 * random token (kept hashed) is the check.
 */
class ZohoSource extends AbstractTokenWatchedSource
{
    private const int CHANNEL_HOURS = 24;

    /**
     * Each reader node's module and kind of change.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const array EVENTS = [
        'zoho_list_new_leads' => ['Leads', 'create'],
        'zoho_list_changed_leads' => ['Leads', 'edit'],
        'zoho_list_new_contacts' => ['Contacts', 'create'],
        'zoho_list_changed_contacts' => ['Contacts', 'edit'],
        'zoho_list_new_accounts' => ['Accounts', 'create'],
        'zoho_list_changed_accounts' => ['Accounts', 'edit'],
        'zoho_list_new_deals' => ['Deals', 'create'],
        'zoho_list_changed_deals' => ['Deals', 'edit'],
        'zoho_list_new_tasks' => ['Tasks', 'create'],
        'zoho_list_changed_tasks' => ['Tasks', 'edit'],
    ];

    public function provider(): string
    {
        return 'zoho';
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
     * Push the channel's expiry out another day; if Zoho no longer has it, make it again.
     */
    public function renewWatch(ProviderWatch $watch): void
    {
        [$http, $api] = $this->client($this->credentialOf($watch));
        $response = $http->put("{$api}/crm/v7/actions/watch", ['watch' => [['channel_id' => (string) $watch->external_id, 'events' => [$watch->resource_key], 'channel_expiry' => $this->expiry(), 'token' => $this->tokenOf($watch), 'notify_url' => $this->urlOf($watch)]]]);

        if (! $response->successful() || $response->json('watch.0.status') !== 'success') {
            $this->register($watch);

            return;
        }

        $watch->renew_at = now()->addHours(self::CHANNEL_HOURS / 2);
    }

    public function deleteWatch(ProviderWatch $watch): void
    {
        [$http, $api] = $this->client($this->credentialOf($watch));
        $response = $http->delete("{$api}/crm/v7/actions/watch?channel_ids=".rawurlencode((string) $watch->external_id));

        if (! in_array($response->status(), [404, 400], true)) {
            $this->ok($response);
        }
    }

    /**
     * Open the channel for the watch's module and change, with a fresh token URL.
     */
    private function register(ProviderWatch $watch): void
    {
        [$http, $api] = $this->client($this->credentialOf($watch));
        $channel = (string) random_int(100000000000000, 999999999999999);

        $this->registerUrl($watch, function (string $url) use ($watch, $http, $api, $channel): void {
            $response = $this->ok($http->post("{$api}/crm/v7/actions/watch", ['watch' => [['channel_id' => $channel, 'events' => [$watch->resource_key], 'channel_expiry' => $this->expiry(), 'token' => hash('sha256', $url), 'notify_url' => $url]]]));

            if ($response->json('watch.0.status') !== 'success') {
                throw new WatchRefusedException('Zoho refused the notification channel: '.($response->json('watch.0.message') ?? 'no reason given').'.');
            }

            $watch->external_id = $channel;
        });

        $watch->expires_at = now()->addHours(self::CHANNEL_HOURS);
        $watch->renew_at = now()->addHours(self::CHANNEL_HOURS / 2);
    }

    private function expiry(): string
    {
        return now()->utc()->addHours(self::CHANNEL_HOURS)->subMinutes(5)->format('Y-m-d\TH:i:s').'+00:00';
    }

    private function urlOf(ProviderWatch $watch): string
    {
        return $this->callbackUrl().'?token='.$watch->secret;
    }

    private function tokenOf(ProviderWatch $watch): string
    {
        return hash('sha256', $this->urlOf($watch));
    }

    /**
     * An authorised client for the account, and the API root of its data center.
     *
     * @return array{0: PendingRequest, 1: string}
     */
    private function client(ConnectorCredential $credential): array
    {
        $login = app(CrmAccessTokens::class)->zoho((array) $credential->data);

        return [Http::withHeaders(['Authorization' => 'Zoho-oauthtoken '.$login['token']])->acceptJson()->timeout(30), $login['api']];
    }
}
