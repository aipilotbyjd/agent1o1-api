<?php

namespace App\Services\Triggers\Push;

use Illuminate\Http\Request;

/**
 * Trello's webhooks: a JSON body with the `action` (id, type, date, data) that
 * happened on the board, posted to a URL with a token. Trello also checks the
 * URL with a HEAD request, which carries nothing to store. The action's id is
 * the delivery id, so a retry is stored once.
 */
class TrelloIngress extends AbstractTokenIngress
{
    public function provider(): string
    {
        return 'trello';
    }

    public function events(Request $request): array
    {
        $action = (array) $request->json('action', []);

        if (($action['id'] ?? '') === '') {
            return [];
        }

        return [[
            'event_id' => (string) $action['id'],
            'event_type' => 'trello.'.($action['type'] ?? 'action'),
            'routing_key' => $this->routingKey($request),
            'payload' => ['action_id' => $action['id'], 'type' => $action['type'] ?? null],
        ]];
    }
}
