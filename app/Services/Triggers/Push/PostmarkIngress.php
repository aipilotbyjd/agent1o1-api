<?php

namespace App\Services\Triggers\Push;

use Illuminate\Http\Request;

/**
 * Postmark's webhooks: a JSON body for a delivery, bounce, spam complaint, open
 * or click, or a received email, posted to a URL with a token. Postmark names
 * the event in `RecordType` (`Bounce`, `Delivery`, `Open`...) and gives a
 * received email no type, only a `MessageID` and `From`.
 */
class PostmarkIngress extends AbstractTokenIngress
{
    public function provider(): string
    {
        return 'postmark';
    }

    public function events(Request $request): array
    {
        $body = $request->json()->all();

        if ($body === []) {
            return [];
        }

        $type = (string) ($body['RecordType'] ?? (isset($body['MailboxHash']) || isset($body['FromFull']) ? 'Inbound' : 'Event'));
        $subject = $body['ID'] ?? $body['MessageID'] ?? sha1($request->getContent());

        return [[
            'event_id' => sha1($this->routingKey($request).'|'.$type.'|'.$subject.'|'.($body['ReceivedAt'] ?? $body['DeliveredAt'] ?? $body['BouncedAt'] ?? $body['Date'] ?? '').'|'.($body['OriginalLink'] ?? '')),
            'event_type' => 'postmark.'.strtolower($type),
            'routing_key' => $this->routingKey($request),
            'payload' => ['type' => $type, 'message_id' => $body['MessageID'] ?? null],
        ]];
    }
}
