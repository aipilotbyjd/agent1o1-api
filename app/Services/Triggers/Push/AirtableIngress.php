<?php

namespace App\Services\Triggers\Push;

use Illuminate\Http\Request;

/**
 * Airtable's webhook pings: a JSON body naming the base and the webhook and the
 * time (`{"base": {"id"}, "webhook": {"id"}, "timestamp"}`), posted to a URL with a
 * token. A ping carries no changes, only the fact that there are some.
 */
class AirtableIngress extends AbstractTokenIngress
{
    public function provider(): string
    {
        return 'airtable';
    }

    public function events(Request $request): array
    {
        $webhook = (string) $request->json('webhook.id');

        if ($webhook === '') {
            return [];
        }

        return [[
            'event_id' => sha1($this->routingKey($request).'|'.$webhook.'|'.$request->json('timestamp')),
            'event_type' => 'airtable.ping',
            'routing_key' => $this->routingKey($request),
            'payload' => ['base_id' => $request->json('base.id')],
        ]];
    }
}
