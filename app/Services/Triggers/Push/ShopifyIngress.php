<?php

namespace App\Services\Triggers\Push;

use App\Contracts\Triggers\ProviderIngress;
use App\Models\Triggers\ProviderWatch;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shopify's webhooks. Every delivery carries `X-Shopify-Hmac-Sha256` (the base64
 * HMAC-SHA256 of the raw body, made with the app's API secret key), the store
 * (`X-Shopify-Shop-Domain`) and the topic (`X-Shopify-Topic`). The request is
 * genuine if the secret of one of that store's watches for that topic signs it,
 * and that watch is where the event is filed.
 *
 * Shopify's `X-Shopify-Event-Id` names the event and stays the same when it sends
 * it again, so a retry is stored once.
 */
class ShopifyIngress implements ProviderIngress
{
    public function provider(): string
    {
        return 'shopify';
    }

    public function verify(Request $request): bool
    {
        return $this->watchOf($request) !== null;
    }

    public function handshake(Request $request): array|Response|null
    {
        return null;
    }

    public function events(Request $request): array
    {
        $watch = $this->watchOf($request);

        if ($watch === null) {
            return [];
        }

        $topic = (string) $request->header('X-Shopify-Topic');
        $key = ShopifySource::routingKey((string) $request->header('X-Shopify-Shop-Domain'), $topic);

        return [[
            'event_id' => (string) ($request->header('X-Shopify-Event-Id') ?: $request->header('X-Shopify-Webhook-Id') ?: sha1($key.'|'.$request->getContent())),
            'event_type' => $topic,
            'routing_key' => $key,
            'payload' => ['topic' => $topic, 'id' => $request->json('id')],
        ]];
    }

    /**
     * The watch whose secret signed the request, if any.
     */
    private function watchOf(Request $request): ?ProviderWatch
    {
        if ($request->attributes->has('shopify_watch')) {
            return $request->attributes->get('shopify_watch');
        }

        return tap($this->findWatch($request), fn (?ProviderWatch $watch) => $request->attributes->set('shopify_watch', $watch));
    }

    private function findWatch(Request $request): ?ProviderWatch
    {
        $shop = strtolower((string) $request->header('X-Shopify-Shop-Domain'));
        $topic = (string) $request->header('X-Shopify-Topic');
        $signature = (string) $request->header('X-Shopify-Hmac-Sha256');

        if ($shop === '' || $topic === '' || $signature === '') {
            return null;
        }

        $watches = ProviderWatch::query()->where('provider', 'shopify')->where('meta->shop', $shop)->where('meta->topic', $topic)->get();

        foreach ($watches as $watch) {
            $secret = $watch->secret;

            if (is_string($secret) && $secret !== '' && hash_equals(base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true)), $signature)) {
                return $watch;
            }
        }

        return null;
    }
}
