<?php

namespace App\Services\Triggers\Push;

use Illuminate\Http\Request;

/**
 * Kit's webhooks: a JSON body naming the subscriber (or purchase), posted to a
 * URL with a token. Kit sends no event name or id of its own, so a delivery is
 * told apart by what it holds: the subscriber or purchase id and, for a tag or
 * form event, the tag or form, plus the time it was received to the second.
 */
class KitIngress extends AbstractTokenIngress
{
    public function provider(): string
    {
        return 'kit';
    }

    public function events(Request $request): array
    {
        $body = $request->json()->all();

        if ($body === []) {
            return [];
        }

        $subject = $body['subscriber']['id'] ?? $body['id'] ?? $body['purchase']['id'] ?? sha1($request->getContent());

        return [[
            'event_id' => sha1($this->routingKey($request).'|'.$subject.'|'.($body['tag']['id'] ?? $body['form']['id'] ?? '').'|'.($body['subscriber']['updated_at'] ?? $body['created_at'] ?? now()->format('Y-m-d H:i:s'))),
            'event_type' => 'kit.event',
            'routing_key' => $this->routingKey($request),
            'payload' => ['subscriber_id' => $body['subscriber']['id'] ?? null],
        ]];
    }
}
