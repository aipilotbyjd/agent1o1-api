<?php

namespace App\Services\Triggers\Push;

use App\Contracts\Triggers\ProviderIngress;
use App\Contracts\Triggers\RepliesToDelivery;
use App\Models\Triggers\ProviderWatch;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Twilio's incoming-message webhook. Twilio signs the full URL followed by every
 * form field (sorted by name) with the account's auth token (HMAC-SHA1, base64)
 * in `X-Twilio-Signature`. The text names the number it was sent to (`To`), which
 * is what the watch is filed under, and the watch names the account whose token
 * checks the signature; the account SID in the request must be that account's.
 *
 * Twilio expects TwiML back: an empty `<Response/>` means "no reply", and any
 * other answer is logged as an error in the Twilio Console.
 */
class TwilioIngress implements ProviderIngress, RepliesToDelivery
{
    public function provider(): string
    {
        return 'twilio';
    }

    public function verify(Request $request): bool
    {
        $number = (string) $request->input('To');
        $signature = (string) $request->header('X-Twilio-Signature');

        if ($number === '' || $signature === '') {
            return false;
        }

        $credential = ProviderWatch::query()->where('provider', 'twilio')->where('external_id', $number)->first()?->credential;
        $token = (string) ($credential?->data['api_key'] ?? '');

        if ($credential === null || $token === '' || (string) $request->input('AccountSid') !== (string) ($credential->data['account_sid'] ?? '')) {
            return false;
        }

        // The URL Twilio called: as this request sees it, or as the app is configured (behind a proxy they can differ).
        $urls = array_unique([$request->fullUrl(), url('/api/hooks/apps/twilio'), rtrim((string) config('app.url'), '/').'/api/hooks/apps/twilio']);

        foreach ($urls as $url) {
            if ($this->signatureIsValid($url, $request->post(), $signature, $token)) {
                return true;
            }
        }

        return false;
    }

    public function handshake(Request $request): array|Response|null
    {
        return null;
    }

    public function events(Request $request): array
    {
        $fields = $request->post();
        $sid = (string) ($fields['MessageSid'] ?? $fields['SmsMessageSid'] ?? '');

        if ($sid === '' || ! filled($fields['To'] ?? null)) {
            return [];
        }

        return [[
            'event_id' => $sid,
            'event_type' => 'sms.received',
            'routing_key' => (string) $fields['To'],
            'payload' => $fields,
        ]];
    }

    /**
     * An empty TwiML answer: nothing to say back.
     */
    public function reply(Request $request): Response
    {
        return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200, ['Content-Type' => 'text/xml']);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function signatureIsValid(string $url, array $fields, string $signature, string $token): bool
    {
        ksort($fields);
        $payload = $url.collect($fields)->map(fn (mixed $value, string $key): string => $key.(is_array($value) ? implode('', $value) : (string) $value))->implode('');

        return hash_equals(base64_encode(hash_hmac('sha1', $payload, $token, true)), $signature);
    }
}
