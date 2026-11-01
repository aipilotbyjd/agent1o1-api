<?php

namespace App\Services\Triggers\Push;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Asana's webhooks. A new webhook is proved by a request carrying
 * `X-Hook-Secret`, which must be answered with the same header. After that a
 * delivery is a JSON body of `events` (a change to a task, a section...), and
 * a heartbeat with none every eight hours, which stores nothing.
 */
class AsanaIngress extends AbstractTokenIngress
{
    public function provider(): string
    {
        return 'asana';
    }

    public function handshake(Request $request): array|Response|null
    {
        $secret = (string) $request->header('X-Hook-Secret');

        return $secret === '' ? null : new Response('', 200, ['X-Hook-Secret' => $secret]);
    }

    public function events(Request $request): array
    {
        $events = array_values(array_filter((array) $request->json('events', []), 'is_array'));

        if ($events === []) {
            return [];
        }

        return [[
            'event_id' => sha1($this->routingKey($request).'|'.$request->getContent()),
            'event_type' => 'asana.events',
            'routing_key' => $this->routingKey($request),
            'payload' => ['count' => count($events), 'first' => ($events[0]['resource']['gid'] ?? null)],
        ]];
    }
}
