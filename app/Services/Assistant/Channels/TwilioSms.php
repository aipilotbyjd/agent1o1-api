<?php

namespace App\Services\Assistant\Channels;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sends texts from the platform's Twilio number, and checks that inbound
 * webhooks really came from Twilio.
 */
class TwilioSms
{
    public function configured(): bool
    {
        return filled(config('assistant.channels.sms.account_sid'))
            && filled(config('assistant.channels.sms.auth_token'))
            && filled(config('assistant.channels.sms.from'));
    }

    public function send(string $to, string $body): void
    {
        $sid = (string) config('assistant.channels.sms.account_sid');

        $response = Http::asForm()
            ->withBasicAuth($sid, (string) config('assistant.channels.sms.auth_token'))
            ->timeout(20)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'To' => $to,
                'From' => config('assistant.channels.sms.from'),
                'Body' => $body,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Twilio refused the text: '.($response->json('message') ?? $response->body()));
        }
    }

    /**
     * Twilio signs the full URL followed by every POST field, sorted by name,
     * with the auth token (HMAC-SHA1, base64).
     *
     * @param  array<string, mixed>  $fields
     */
    public function signatureIsValid(string $url, array $fields, string $signature): bool
    {
        $token = (string) config('assistant.channels.sms.auth_token');

        if ($token === '' || $signature === '') {
            return false;
        }

        ksort($fields);
        $payload = $url.collect($fields)->map(fn ($value, string $key): string => $key.(is_array($value) ? implode('', $value) : (string) $value))->implode('');

        return hash_equals(base64_encode(hash_hmac('sha1', $payload, $token, true)), $signature);
    }
}
