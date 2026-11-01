<?php

namespace App\Services\Triggers\Push;

use App\Exceptions\WatchRefusedException;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use App\Nodes\Integrations\Shopify\AbstractShopifyNode;
use App\Nodes\Integrations\Shopify\AbstractShopifyTriggerNode;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Shopify triggers through its webhooks.
 *
 * One webhook per topic (`orders/create`, `products/update`...) and connected
 * store, shared by every trigger that watches that topic. Shopify signs each
 * delivery with the app's API secret key (`X-Shopify-Hmac-Sha256`), which is a
 * field of the connection, so a store connected without it is refused with a
 * sentence and its triggers keep polling. A delivery only makes the trigger's own
 * poll look now, so the poll's filters, order and dedupe still decide what runs.
 *
 * Shopify removes a webhook after a day of failed deliveries, so the daily renewal
 * makes it again when it is gone, and points it at the right address if
 * `APP_URL` changed.
 */
class ShopifySource extends AbstractWatchedSource
{
    public function provider(): string
    {
        return 'shopify';
    }

    protected function nodeTypes(): array
    {
        return [
            'shopify_list_orders', 'shopify_order_paid', 'shopify_order_cancelled', 'shopify_order_updated',
            'shopify_list_customers', 'shopify_customer_updated', 'shopify_list_products', 'shopify_product_updated',
        ];
    }

    public function watchKeyFor(Trigger $trigger): ?string
    {
        $topic = $this->topicOf($trigger);

        return $topic === null ? null : 'topic:'.$topic;
    }

    public function createWatch(ProviderWatch $watch, Trigger $trigger): void
    {
        $this->register($watch, substr($watch->resource_key, strlen('topic:')));
    }

    /**
     * Shopify drops a webhook that keeps failing: make it again if it is gone, point
     * it at the right address, and keep the secret current.
     */
    public function renewWatch(ProviderWatch $watch): void
    {
        $credential = $this->credentialOf($watch);
        $shop = $this->shopOf($credential);
        $response = $this->shopify($credential)->get($this->base($shop)."/webhooks/{$watch->external_id}.json");

        if ($response->status() === 404) {
            $this->register($watch, (string) ($watch->meta['topic'] ?? ''));

            return;
        }

        $this->ok($response);

        if ($response->json('webhook.address') !== $this->callbackUrl()) {
            $this->ok($this->shopify($credential)->put($this->base($shop)."/webhooks/{$watch->external_id}.json", ['webhook' => ['id' => (int) $watch->external_id, 'address' => $this->callbackUrl()]]));
        }

        $watch->secret = $this->secretOf($credential);
        $watch->renew_at = now()->addDay();
    }

    public function deleteWatch(ProviderWatch $watch): void
    {
        $credential = $this->credentialOf($watch);
        $response = $this->shopify($credential)->delete($this->base($this->shopOf($credential))."/webhooks/{$watch->external_id}.json");

        if ($response->status() !== 404) {
            $this->ok($response);
        }
    }

    /**
     * Deliveries name the store and the topic.
     */
    public function routingKeyFor(Trigger $trigger, ?ProviderWatch $watch = null): ?string
    {
        return $watch === null ? null : self::routingKey((string) ($watch->meta['shop'] ?? ''), (string) ($watch->meta['topic'] ?? ''));
    }

    /**
     * What a watch's events are filed under, and what a delivery says it is for.
     */
    public static function routingKey(string $shop, string $topic): string
    {
        return strtolower($shop).'|'.$topic;
    }

    /**
     * Make the webhook for the topic, or take over the one that is already there
     * for the same address, and keep what the deliveries are verified with.
     *
     * @throws WatchRefusedException when the connection can't make an instant trigger
     * @throws RequestException when Shopify refuses the webhook
     */
    private function register(ProviderWatch $watch, string $topic): void
    {
        $credential = $this->credentialOf($watch);
        $shop = $this->shopOf($credential);
        $secret = $this->secretOf($credential);

        if ($topic === '') {
            throw new WatchRefusedException('This trigger has no Shopify event to listen to.');
        }

        if ($secret === null) {
            throw new WatchRefusedException('Add the app\'s API secret key to the Shopify connection to make this trigger instant. Until then it checks every few minutes.');
        }

        $response = $this->shopify($credential)->post($this->base($shop).'/webhooks.json', ['webhook' => ['topic' => $topic, 'address' => $this->callbackUrl(), 'format' => 'json']]);

        if ($response->status() === 422) {
            // The app already has this topic going to this address: use that one.
            $existing = collect((array) $this->ok($this->shopify($credential)->get($this->base($shop).'/webhooks.json', ['topic' => $topic, 'address' => $this->callbackUrl()]))->json('webhooks'))->first();
            $id = is_array($existing) ? ($existing['id'] ?? null) : null;

            if ($id === null) {
                throw new WatchRefusedException('Shopify refused the webhook: '.(string) json_encode($response->json('errors')));
            }
        } else {
            $id = $this->ok($response)->json('webhook.id');
        }

        $watch->external_id = (string) $id;
        $watch->secret = $secret;
        $watch->meta = [...(array) $watch->meta, 'shop' => $shop, 'topic' => $topic];
        $watch->expires_at = null;
        $watch->renew_at = now()->addDay();
    }

    /**
     * The webhook topic of the trigger's node.
     */
    private function topicOf(Trigger $trigger): ?string
    {
        $node = app(NodeRegistry::class)->resolve((string) ($trigger->config['node_type'] ?? ''));

        return $node instanceof AbstractShopifyTriggerNode ? $node->webhookTopic() : null;
    }

    /**
     * @throws WatchRefusedException when the store address isn't one
     */
    private function shopOf(ConnectorCredential $credential): string
    {
        return AbstractShopifyNode::normalizeShop((string) ($credential->data['shop'] ?? ''))
            ?? throw new WatchRefusedException('The Shopify connection needs the store address, like your-store.myshopify.com.');
    }

    private function secretOf(ConnectorCredential $credential): ?string
    {
        $secret = trim((string) ($credential->data['api_secret'] ?? ''));

        return $secret === '' ? null : $secret;
    }

    private function base(string $shop): string
    {
        return "https://{$shop}/admin/api/".AbstractShopifyNode::API_VERSION;
    }

    /**
     * Shopify takes the token in its own header and the body as JSON.
     */
    private function shopify(ConnectorCredential $credential): PendingRequest
    {
        return Http::withHeaders(['X-Shopify-Access-Token' => $this->tokens->accessToken($credential)])->acceptJson()->asJson()->timeout(20);
    }
}
