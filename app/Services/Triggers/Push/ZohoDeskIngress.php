<?php

namespace App\Services\Triggers\Push;

use Illuminate\Http\Request;

/**
 * Zoho Desk's webhooks: a JSON list of events (`eventType`, `payload`), posted to
 * a URL with a token. A delivery carries no id of its own, so the id is made from
 * the token and the content; Desk retries a failed delivery with the same body, so
 * a retry is stored once.
 */
class ZohoDeskIngress extends AbstractTokenIngress
{
    public function provider(): string
    {
        return 'zoho_desk';
    }

    public function events(Request $request): array
    {
        $events = $request->json()->all();
        $events = array_is_list($events) ? $events : [$events];
        $found = [];

        foreach ($events as $event) {
            if (! is_array($event) || ! filled($event['eventType'] ?? null)) {
                continue;
            }

            $found[] = [
                'event_id' => sha1($this->routingKey($request).'|'.json_encode($event)),
                'event_type' => 'zoho_desk.'.strtolower((string) $event['eventType']),
                'routing_key' => $this->routingKey($request),
                'payload' => ['event' => $event['eventType'], 'ticket_id' => data_get($event, 'payload.id') ?? data_get($event, 'payload.ticketId')],
            ];
        }

        return $found;
    }
}
