<?php

namespace App\Services\Triggers\Push;

use Illuminate\Http\Request;

/**
 * Resend's webhooks: a JSON body with a `type` (`email.bounced`) and `data`
 * (the email or contact), posted to a URL with a token. Resend's delivery id
 * (`svix-id`) is the event id, so a retry is stored once.
 */
class ResendIngress extends AbstractTokenIngress
{
    public function provider(): string
    {
        return 'resend';
    }

    public function events(Request $request): array
    {
        $type = (string) $request->json('type');

        if ($type === '') {
            return [];
        }

        return [[
            'event_id' => (string) ($request->header('svix-id') ?: sha1($request->getContent())),
            'event_type' => $type,
            'routing_key' => $this->routingKey($request),
            'payload' => ['type' => $type, 'id' => $request->json('data.email_id') ?? $request->json('data.id')],
        ]];
    }
}
