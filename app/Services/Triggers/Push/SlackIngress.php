<?php

namespace App\Services\Triggers\Push;

use App\Contracts\Triggers\ProviderIngress;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Slack's Events API request URL: checks Slack's signature, answers the URL
 * check, and lists the events in a delivery.
 *
 * Slack signs `v0:{timestamp}:{body}` with the app's signing secret and expects
 * a reply within three seconds, so nothing here touches the network.
 */
class SlackIngress implements ProviderIngress
{
    /**
     * The provider key.
     */
    public function provider(): string
    {
        return 'slack';
    }

    /**
     * Whether Slack signed the request with our secret, recently.
     */
    public function verify(Request $request): bool
    {
        $secret = (string) config('triggers.push.slack.signing_secret');
        $timestamp = (string) $request->header('X-Slack-Request-Timestamp');
        $signature = (string) $request->header('X-Slack-Signature');

        if ($secret === '' || $timestamp === '' || $signature === '') {
            return false;
        }

        if (abs(time() - (int) $timestamp) > (int) config('triggers.push.slack.max_age_seconds')) {
            return false;
        }

        return hash_equals('v0='.hash_hmac('sha256', "v0:{$timestamp}:{$request->getContent()}", $secret), $signature);
    }

    /**
     * The `url_verification` challenge.
     */
    public function handshake(Request $request): array|Response|null
    {
        if ($request->json('type') !== 'url_verification') {
            return null;
        }

        return ['challenge' => (string) $request->json('challenge', '')];
    }

    /**
     * The one event of an `event_callback`, filed under its team.
     */
    public function events(Request $request): array
    {
        $body = $request->json()->all();
        $event = $body['event'] ?? null;

        if (($body['type'] ?? null) !== 'event_callback' || ! is_array($event) || ! filled($body['event_id'] ?? null)) {
            return [];
        }

        return [[
            'event_id' => (string) $body['event_id'],
            'event_type' => (string) ($event['type'] ?? 'unknown'),
            'routing_key' => filled($body['team_id'] ?? null) ? (string) $body['team_id'] : null,
            'payload' => $event,
        ]];
    }
}
