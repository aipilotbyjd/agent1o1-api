<?php

namespace App\Http\Controllers\Webhooks;

use App\Contracts\Triggers\RepliesToDelivery;
use App\Http\Controllers\Controller;
use App\Jobs\Triggers\RouteProviderEvent;
use App\Models\Triggers\ProviderEvent;
use App\Services\Triggers\Push\PushSources;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `POST /api/hooks/apps/{provider}`: where a provider delivers events for
 * every connected account at once (Slack's Events API request URL).
 *
 * Verifies the request, answers a handshake, stores each event in the inbox
 * and queues its routing, then answers the provider straight away: providers
 * give a few seconds to reply and retry when we are slow. A retried event is
 * stored once (the unique provider event id).
 */
class ProviderIngressController extends Controller
{
    public function __invoke(Request $request, string $provider, PushSources $sources): Response
    {
        $ingress = $sources->ingress($provider);

        abort_if($ingress === null, 404);
        abort_unless($ingress->verify($request), 401, 'Invalid signature.');

        $handshake = $ingress->handshake($request);

        if ($handshake instanceof Response) {
            return $handshake;
        }

        if (is_array($handshake)) {
            return response()->json($handshake);
        }

        foreach ($ingress->events($request) as $incoming) {
            try {
                $event = ProviderEvent::query()->firstOrCreate(
                    ['provider' => $provider, 'event_id' => $incoming['event_id']],
                    [
                        'event_type' => $incoming['event_type'],
                        'routing_key' => $incoming['routing_key'],
                        'payload' => $incoming['payload'],
                        'received_at' => now(),
                    ],
                );
            } catch (UniqueConstraintViolationException) {
                // A retry of the same event arrived at the same moment.
                continue;
            }

            // The provider retried an event we already hold.
            if (! $event->wasRecentlyCreated) {
                continue;
            }

            RouteProviderEvent::dispatch($event);
        }

        if ($ingress instanceof RepliesToDelivery) {
            $reply = $ingress->reply($request);

            return $reply instanceof Response ? $reply : response()->json($reply);
        }

        return response()->json(['ok' => true]);
    }
}
