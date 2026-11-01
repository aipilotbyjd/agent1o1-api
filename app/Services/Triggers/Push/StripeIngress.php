<?php

namespace App\Services\Triggers\Push;

use App\Contracts\Triggers\ProviderIngress;
use App\Models\Triggers\ProviderWatch;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stripe's webhook endpoints. Every delivery carries `Stripe-Signature:
 * t=<unix time>,v1=<HMAC-SHA256 of "time.body">`, made with the signing secret
 * of the endpoint that sent it (more than one `v1` while a secret is being
 * rolled). The body doesn't say which endpoint it is for, so the request is
 * genuine if one of the Stripe watches' secrets signs it, and that watch is
 * where the event is filed. A delivery older than five minutes is refused, so
 * a captured one can't be replayed.
 *
 * Stripe's event id is the event id here: a retry is stored once.
 */
class StripeIngress implements ProviderIngress
{
    private const int TOLERANCE_SECONDS = 300;

    public function provider(): string
    {
        return 'stripe';
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
        $id = (string) $request->json('id');

        if ($watch === null || $id === '') {
            return [];
        }

        return [[
            'event_id' => $id,
            'event_type' => (string) $request->json('type', 'event'),
            'routing_key' => $watch->external_id,
            'payload' => ['id' => $id, 'type' => $request->json('type')],
        ]];
    }

    /**
     * The watch whose secret signed the request, if any.
     */
    private function watchOf(Request $request): ?ProviderWatch
    {
        if ($request->attributes->has('stripe_watch')) {
            return $request->attributes->get('stripe_watch');
        }

        return tap($this->findWatch($request), fn (?ProviderWatch $watch) => $request->attributes->set('stripe_watch', $watch));
    }

    private function findWatch(Request $request): ?ProviderWatch
    {
        $pairs = [];

        foreach (explode(',', (string) $request->header('Stripe-Signature')) as $pair) {
            [$key, $value] = array_pad(explode('=', trim($pair), 2), 2, null);

            if ($key !== null && $value !== null) {
                $pairs[$key][] = $value;
            }
        }

        $timestamp = $pairs['t'][0] ?? '';
        $signatures = $pairs['v1'] ?? [];

        if (! ctype_digit($timestamp) || $signatures === [] || abs(time() - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            return null;
        }

        foreach (ProviderWatch::query()->where('provider', 'stripe')->get() as $watch) {
            $secret = $watch->secret;

            if (! is_string($secret) || $secret === '') {
                continue;
            }

            $expected = hash_hmac('sha256', "{$timestamp}.{$request->getContent()}", $secret);

            foreach ($signatures as $signature) {
                if (hash_equals($expected, (string) $signature)) {
                    return $watch;
                }
            }
        }

        return null;
    }
}
