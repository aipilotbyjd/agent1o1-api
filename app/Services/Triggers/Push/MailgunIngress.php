<?php

namespace App\Services\Triggers\Push;

use Illuminate\Http\Request;

/**
 * Mailgun's webhooks: a JSON body with a `signature` and the `event-data` (event
 * name, id, recipient, message), posted to a URL with a token. The event's own id
 * is the delivery id, so a retry is stored once.
 */
class MailgunIngress extends AbstractTokenIngress
{
    public function provider(): string
    {
        return 'mailgun';
    }

    public function events(Request $request): array
    {
        $data = (array) $request->json('event-data', []);
        $event = (string) ($data['event'] ?? '');

        if ($event === '') {
            return [];
        }

        return [[
            'event_id' => (string) ($data['id'] ?? sha1($request->getContent())),
            'event_type' => 'mailgun.'.$event,
            'routing_key' => $this->routingKey($request),
            'payload' => ['event' => $event, 'recipient' => $data['recipient'] ?? null],
        ]];
    }
}
