<?php

namespace App\Services\Triggers\Push;

use App\Contracts\Triggers\ProviderIngress;
use App\Models\Triggers\ProviderWatch;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Linear webhooks. Each delivery names its webhook (`webhookId`) and is signed
 * with that webhook's secret (`Linear-Signature`, an HMAC-SHA256 of the body in
 * hex). The delivery's own timestamp must be recent, so a captured request
 * can't be replayed later; a retry Linear makes hours on is refused, and the
 * trigger's poll covers it.
 */
class LinearIngress implements ProviderIngress
{
    public function provider(): string
    {
        return 'linear';
    }

    public function verify(Request $request): bool
    {
        $webhookId = (string) $request->json('webhookId');
        $signature = (string) $request->header('Linear-Signature');
        $sentAt = (int) $request->json('webhookTimestamp');

        if ($webhookId === '' || $signature === '' || abs(now()->getTimestampMs() - $sentAt) > (int) config('triggers.push.linear.max_age_seconds', 300) * 1000) {
            return false;
        }

        $secret = ProviderWatch::query()->where('provider', 'linear')->where('external_id', $webhookId)->first()?->secret;

        return is_string($secret) && hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature);
    }

    /**
     * Linear has no handshake: a webhook is live once it is created.
     */
    public function handshake(Request $request): array|Response|null
    {
        return null;
    }

    public function events(Request $request): array
    {
        $webhookId = (string) $request->json('webhookId');
        $type = (string) $request->json('type');

        if ($webhookId === '' || $type === '') {
            return [];
        }

        return [[
            'event_id' => (string) ($request->header('Linear-Delivery') ?: sha1($request->getContent())),
            'event_type' => $type.'.'.(string) $request->json('action'),
            'routing_key' => $webhookId,
            'payload' => ['event' => $type, 'action' => $request->json('action'), 'id' => $request->json('data.id')],
        ]];
    }
}
