<?php

namespace App\Services\Triggers\Push;

use Illuminate\Http\Request;

/**
 * Pipedrive's webhooks: a JSON body with `meta` (the object, the action, the time
 * and the record's id) and the record, posted to a URL with a token. The record id
 * alone repeats for every change to it, so the event's time is part of the delivery
 * id; Pipedrive retries a failed delivery with the same time, so a retry is stored once.
 */
class PipedriveIngress extends AbstractTokenIngress
{
    public function provider(): string
    {
        return 'pipedrive';
    }

    public function events(Request $request): array
    {
        $meta = (array) $request->json('meta', []);
        $object = (string) ($meta['object'] ?? '');

        if ($object === '') {
            return [];
        }

        return [[
            'event_id' => sha1($this->routingKey($request).'|'.$object.'|'.($meta['action'] ?? '').'|'.($meta['id'] ?? '').'|'.($meta['timestamp_micro'] ?? $meta['timestamp'] ?? sha1($request->getContent()))),
            'event_type' => 'pipedrive.'.$object.'.'.($meta['action'] ?? 'event'),
            'routing_key' => $this->routingKey($request),
            'payload' => ['object' => $object, 'action' => $meta['action'] ?? null, 'record_id' => $meta['id'] ?? null],
        ]];
    }
}
