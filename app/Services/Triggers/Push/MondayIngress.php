<?php

namespace App\Services\Triggers\Push;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * monday.com's webhooks. A new webhook is proved by a body of `{"challenge": …}`,
 * which must be answered with the same body. After that a delivery is a JSON body
 * with an `event` (the type, board, item and a trigger id), posted to a URL with a
 * token.
 */
class MondayIngress extends AbstractTokenIngress
{
    public function provider(): string
    {
        return 'monday';
    }

    public function handshake(Request $request): array|Response|null
    {
        $challenge = $request->json('challenge');

        return is_string($challenge) && $challenge !== '' ? ['challenge' => $challenge] : null;
    }

    public function events(Request $request): array
    {
        $event = (array) $request->json('event', []);

        if (($event['type'] ?? '') === '') {
            return [];
        }

        return [[
            'event_id' => (string) ($event['triggerUuid'] ?? sha1($this->routingKey($request).'|'.$request->getContent())),
            'event_type' => 'monday.'.$event['type'],
            'routing_key' => $this->routingKey($request),
            'payload' => ['type' => $event['type'], 'item_id' => $event['pulseId'] ?? $event['itemId'] ?? null],
        ]];
    }
}
