<?php

namespace App\Services\Triggers\Push;

use Illuminate\Http\Request;

/**
 * ActiveCampaign's webhooks, posted as a form (`type`, `date_time`, `contact[id]`
 * or `deal[id]`, …) to a URL with a token. ActiveCampaign has no event id, so a
 * delivery is told apart by its type, subject and time.
 */
class ActiveCampaignIngress extends AbstractTokenIngress
{
    public function provider(): string
    {
        return 'activecampaign';
    }

    public function events(Request $request): array
    {
        $type = (string) $request->input('type');

        if ($type === '') {
            return [];
        }

        $subject = $request->input('contact.id') ?? $request->input('deal.id') ?? '';

        return [[
            'event_id' => sha1($this->routingKey($request).'|'.$type.'|'.$subject.'|'.$request->input('date_time').'|'.$request->input('contact.email')),
            'event_type' => $type,
            'routing_key' => $this->routingKey($request),
            'payload' => ['type' => $type, 'id' => $subject],
        ]];
    }
}
