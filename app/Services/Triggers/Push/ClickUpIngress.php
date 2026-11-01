<?php

namespace App\Services\Triggers\Push;

use Illuminate\Http\Request;

/**
 * ClickUp's webhooks: a JSON body with the `event` (`taskCreated`), the
 * `task_id` and the `history_items` that describe the change, posted to a URL
 * with a token. The first history item's id (or, for a created task, the task) is
 * what tells two deliveries apart.
 */
class ClickUpIngress extends AbstractTokenIngress
{
    public function provider(): string
    {
        return 'clickup';
    }

    public function events(Request $request): array
    {
        $event = (string) $request->json('event');

        if ($event === '') {
            return [];
        }

        $item = (string) ($request->json('history_items.0.id') ?? sha1($request->getContent()));

        return [[
            'event_id' => sha1($this->routingKey($request).'|'.$event.'|'.$request->json('task_id').'|'.$item),
            'event_type' => 'clickup.'.$event,
            'routing_key' => $this->routingKey($request),
            'payload' => ['event' => $event, 'task_id' => $request->json('task_id')],
        ]];
    }
}
