<?php

namespace App\Services\Triggers\Push;

use App\Contracts\Triggers\ProviderIngress;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Pub/Sub push subscription Google Forms publishes to. Pub/Sub is told to
 * call `/api/hooks/apps/google_forms?token=...`, and the token is the whole
 * check. A message names the form (and the kind of event) in its attributes.
 */
class GoogleFormsIngress implements ProviderIngress
{
    public function provider(): string
    {
        return 'google_forms';
    }

    public function verify(Request $request): bool
    {
        $expected = (string) config('triggers.push.google_forms.verification_token');

        return $expected !== '' && hash_equals($expected, (string) $request->query('token'));
    }

    public function handshake(Request $request): array|Response|null
    {
        return null;
    }

    /**
     * Only new responses start triggers; a changed form (its questions) does not.
     */
    public function events(Request $request): array
    {
        $message = $request->json('message');
        $attributes = is_array($message) ? (array) ($message['attributes'] ?? []) : [];

        if (! filled($message['messageId'] ?? null) || ! filled($attributes['formId'] ?? null) || ($attributes['eventType'] ?? null) !== 'RESPONSES') {
            return [];
        }

        return [[
            'event_id' => (string) $message['messageId'],
            'event_type' => 'responses',
            'routing_key' => (string) $attributes['formId'],
            'payload' => ['form_id' => $attributes['formId']],
        ]];
    }
}
