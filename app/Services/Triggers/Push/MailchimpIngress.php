<?php

namespace App\Services\Triggers\Push;

use Illuminate\Http\Request;

/**
 * Mailchimp's audience webhooks, sent as a form (`type`, `fired_at`, `data[...]`)
 * to a URL with a token. Mailchimp also calls the URL without an event to check
 * it, which carries nothing to store.
 */
class MailchimpIngress extends AbstractTokenIngress
{
    public function provider(): string
    {
        return 'mailchimp';
    }

    public function events(Request $request): array
    {
        $type = (string) $request->input('type');

        if ($type === '') {
            return [];
        }

        $data = (array) $request->input('data', []);

        return [[
            'event_id' => sha1($type.'|'.$request->input('fired_at').'|'.($data['email'] ?? $data['id'] ?? '').'|'.($data['list_id'] ?? '').'|'.$this->routingKey($request)),
            'event_type' => $type,
            'routing_key' => $this->routingKey($request),
            'payload' => ['type' => $type, 'list_id' => $data['list_id'] ?? null],
        ]];
    }
}
