<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\Assistant\HandleSlackMessageJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Slack Events API for the platform's Slack app: verifies Slack's
 * signature, answers the URL check, and queues DMs to the assistant.
 * Slack retries events it thinks were missed, so each is handled once.
 */
class AssistantSlackEventsController extends Controller
{
    private const int MAX_AGE_SECONDS = 300;

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($this->signedBySlack($request), 401, 'Invalid Slack signature.');

        $payload = $request->json()->all();

        if (($payload['type'] ?? null) === 'url_verification') {
            return response()->json(['challenge' => $payload['challenge'] ?? '']);
        }

        $event = $payload['event'] ?? [];
        $eventId = (string) ($payload['event_id'] ?? '');

        if ($this->isDirectMessage($event) && ($eventId === '' || Cache::add("assistant-slack-event:{$eventId}", true, 3600))) {
            HandleSlackMessageJob::dispatch((string) ($payload['team_id'] ?? ''), $event);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * A person's own message in a DM, not the bot's replies, edits or joins.
     *
     * @param  array<string, mixed>  $event
     */
    private function isDirectMessage(array $event): bool
    {
        return ($event['type'] ?? null) === 'message'
            && ($event['channel_type'] ?? null) === 'im'
            && ! isset($event['bot_id'])
            && ! isset($event['subtype'])
            && isset($event['user'], $event['channel'], $event['ts']);
    }

    private function signedBySlack(Request $request): bool
    {
        $secret = (string) config('assistant.channels.slack.signing_secret');
        $timestamp = (string) $request->header('X-Slack-Request-Timestamp');
        $signature = (string) $request->header('X-Slack-Signature');

        if ($secret === '' || $timestamp === '' || $signature === '' || abs(time() - (int) $timestamp) > self::MAX_AGE_SECONDS) {
            return false;
        }

        return hash_equals('v0='.hash_hmac('sha256', "v0:{$timestamp}:{$request->getContent()}", $secret), $signature);
    }
}
