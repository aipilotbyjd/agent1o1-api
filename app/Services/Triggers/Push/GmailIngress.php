<?php

namespace App\Services\Triggers\Push;

use App\Contracts\Triggers\ProviderIngress;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Pub/Sub push subscription Gmail's watch publishes to. Pub/Sub is told to
 * call `/api/hooks/apps/gmail?token=...`, and the token is the whole check.
 *
 * A message carries only the mailbox address and a history id; the trigger's
 * poll finds the actual mail.
 */
class GmailIngress implements ProviderIngress
{
    public function provider(): string
    {
        return 'gmail';
    }

    public function verify(Request $request): bool
    {
        $expected = (string) config('triggers.push.gmail.verification_token');

        return $expected !== '' && hash_equals($expected, (string) $request->query('token'));
    }

    public function handshake(Request $request): array|Response|null
    {
        return null;
    }

    public function events(Request $request): array
    {
        $message = $request->json('message');

        if (! is_array($message) || ! filled($message['messageId'] ?? null)) {
            return [];
        }

        $data = json_decode((string) base64_decode((string) ($message['data'] ?? ''), true), true);

        if (! is_array($data) || ! filled($data['emailAddress'] ?? null)) {
            return [];
        }

        return [[
            'event_id' => (string) $message['messageId'],
            'event_type' => 'mailbox_changed',
            'routing_key' => strtolower((string) $data['emailAddress']),
            'payload' => ['history_id' => $data['historyId'] ?? null],
        ]];
    }
}
