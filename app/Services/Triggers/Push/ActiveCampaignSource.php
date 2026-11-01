<?php

namespace App\Services\Triggers\Push;

use App\Exceptions\ConnectorException;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use App\Nodes\Integrations\ActiveCampaign\AbstractActiveCampaignNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * ActiveCampaign triggers through its webhooks.
 *
 * One webhook per account and set of events (new contacts, edited contacts, new
 * deals), shared by every trigger that wants those events. ActiveCampaign does
 * not sign webhooks, so the URL carries a random token (kept hashed); a delivery
 * only makes the trigger's own poll look now. A webhook someone deleted is made
 * again by the daily renewal.
 */
class ActiveCampaignSource extends AbstractTokenWatchedSource
{
    /**
     * The webhook events each reader node listens for.
     *
     * @var array<string, list<string>>
     */
    private const array EVENTS = [
        'activecampaign_list_contacts' => ['subscribe'],
        'activecampaign_list_updated_contacts' => ['subscribe', 'update'],
        'activecampaign_list_deals' => ['deal_add'],
    ];

    public function provider(): string
    {
        return 'activecampaign';
    }

    protected function nodeTypes(): array
    {
        return array_keys(self::EVENTS);
    }

    public function watchKeyFor(Trigger $trigger): ?string
    {
        $events = self::EVENTS[$trigger->config['node_type'] ?? ''] ?? null;

        return $events === null ? null : 'events:'.implode(',', $events);
    }

    public function createWatch(ProviderWatch $watch, Trigger $trigger): void
    {
        $this->register($watch);
    }

    /**
     * ActiveCampaign never expires a webhook, but someone may have deleted it: make it again.
     */
    public function renewWatch(ProviderWatch $watch): void
    {
        $credential = $this->credentialOf($watch);
        $response = $this->activeCampaign($credential)->get($this->root($credential).'/webhooks/'.$watch->external_id);

        if ($response->status() === 404) {
            $this->register($watch);

            return;
        }

        $this->ok($response);
        $watch->renew_at = now()->addDay();
    }

    public function deleteWatch(ProviderWatch $watch): void
    {
        $credential = $this->credentialOf($watch);
        $response = $this->activeCampaign($credential)->delete($this->root($credential).'/webhooks/'.$watch->external_id);

        if ($response->status() !== 404) {
            $this->ok($response);
        }
    }

    /**
     * Make the webhook for the watch's events, with a fresh token URL.
     */
    private function register(ProviderWatch $watch): void
    {
        $events = explode(',', substr($watch->resource_key, strlen('events:')));

        $this->registerUrl($watch, function (string $url) use ($watch, $events): void {
            $credential = $this->credentialOf($watch);
            $response = $this->ok($this->activeCampaign($credential)->post($this->root($credential).'/webhooks', ['webhook' => [
                'name' => 'Flows',
                'url' => $url,
                'events' => $events,
                'sources' => ['public', 'system', 'admin', 'api'],
            ]]));

            $watch->external_id = (string) $response->json('webhook.id');
        });

        $watch->expires_at = null;
        $watch->renew_at = now()->addDay();
    }

    /**
     * The API root of the connected account, checked against ActiveCampaign's domains.
     *
     * @throws ConnectorException when the connection's address is wrong
     */
    private function root(ConnectorCredential $credential): string
    {
        try {
            return AbstractActiveCampaignNode::apiRoot((string) ($credential->data['api_url'] ?? ''));
        } catch (InvalidArgumentException $exception) {
            throw new ConnectorException($exception->getMessage());
        }
    }

    /**
     * ActiveCampaign takes the key in a header.
     */
    private function activeCampaign(ConnectorCredential $credential): PendingRequest
    {
        return Http::withHeaders(['Api-Token' => $this->tokens->accessToken($credential)])->acceptJson()->timeout(20);
    }
}
