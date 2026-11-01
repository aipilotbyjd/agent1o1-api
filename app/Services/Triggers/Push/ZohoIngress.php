<?php

namespace App\Services\Triggers\Push;

use Illuminate\Http\Request;

/**
 * Zoho CRM's notifications: a JSON body naming the module, the operation (`insert`,
 * `update`, `delete`), the ids of the records and the channel, posted to a URL with
 * a token. Zoho sends the time of the notification, so two changes to one record are
 * two events and a retry is stored once.
 */
class ZohoIngress extends AbstractTokenIngress
{
    public function provider(): string
    {
        return 'zoho';
    }

    public function events(Request $request): array
    {
        $module = (string) $request->json('module');

        if ($module === '') {
            return [];
        }

        return [[
            'event_id' => sha1($this->routingKey($request).'|'.$request->json('channel_id').'|'.$request->json('operation').'|'.json_encode($request->json('ids')).'|'.$request->json('server_time')),
            'event_type' => 'zoho.'.strtolower($module).'.'.($request->json('operation') ?? 'event'),
            'routing_key' => $this->routingKey($request),
            'payload' => ['module' => $module, 'operation' => $request->json('operation'), 'ids' => (array) $request->json('ids', [])],
        ]];
    }
}
