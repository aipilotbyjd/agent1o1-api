<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\Triggers\TriggerEventStatus;
use App\Enums\Triggers\TriggerType;
use App\Enums\Triggers\WebhookAuthentication;
use App\Http\Controllers\Controller;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerEvent;
use App\Services\Triggers\TriggerService;
use App\Services\Triggers\WebhookSignatureVerifier;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public, token-authenticated: see docs/TRIGGERS_PLAN.md's "Why webhooks.php
 * sits outside routes/api/{internal,public}/". No session, no API key: the
 * `{token}` path segment against `triggers.token` is the entire auth story.
 */
class WebhookController extends Controller
{
    public function __construct(
        private readonly WebhookSignatureVerifier $verifier,
        private readonly TriggerService $triggers,
    ) {}

    public function __invoke(string $token, Request $request): Response
    {
        $trigger = Trigger::query()
            ->where('token', $token)
            ->where('type', TriggerType::Webhook)
            ->where('is_active', true)
            ->first();

        abort_if($trigger === null, 404);

        // A provider checking that the URL is really ours (Meta's GET
        // challenge), answered before anything is stored.
        if ($request->isMethod('GET')) {
            return $this->answerGetChallenge($trigger, $request);
        }

        $rawBody = $request->getContent();

        // Checked before anything is parsed or stored: a payload this size is
        // never a legitimate event, and every byte would be written to
        // trigger_events and handed to the run as input.
        if (strlen($rawBody) > (int) config('triggers.max_webhook_body_kb') * 1024) {
            return response('Payload too large.', 413);
        }
        $headers = $this->flattenHeaders($request);
        $snippet = substr($rawBody, 0, (int) config('triggers.payload_snippet_chars'));

        if (! $this->verifier->verify($trigger, $request, $rawBody)) {
            $this->triggers->reject(
                $trigger,
                TriggerType::Webhook,
                (array) $request->all(),
                $snippet,
                $headers,
                $this->usesHeaderToken($trigger) ? 'Token verification failed.' : 'Signature verification failed.',
            );

            return response($this->usesHeaderToken($trigger) ? 'Invalid token.' : 'Invalid signature.', 401);
        }

        // Slack's one-off "is this URL ours" POST: echo the challenge back. It
        // isn't an event, so it starts nothing.
        if ($request->input('type') === 'url_verification' && is_string($request->input('challenge'))) {
            return response()->json(['challenge' => $request->input('challenge')]);
        }

        $event = $this->triggers->receive(
            $trigger,
            TriggerType::Webhook,
            $this->payloadFor($trigger, $request, $rawBody),
            $this->deliveryId($trigger, $request),
            $snippet,
            $headers,
        );

        // Ignored/duplicate/skipped all answer 200 too: providers fan every
        // event at one URL, and a non-2xx on an unwanted event causes retries,
        // then hook disabling. See docs/TRIGGERS_PLAN.md's design decisions.
        if (($trigger->config['response_mode'] ?? 'immediate') === 'wait') {
            return $this->respondWithResult($trigger, $event);
        }

        return response()->json([
            'status' => 'accepted',
            'event_id' => $event->id,
            'run_id' => $event->run_id,
            'duplicate' => ! $event->wasRecentlyCreated,
        ], 200);
    }

    /**
     * Answer a GET: only Meta's `hub.mode=subscribe` verification, and only
     * once the trigger has a secret to check `hub.verify_token` against (the
     * trigger's signing secret). Anything else is a 404, as if there were no
     * such URL.
     */
    private function answerGetChallenge(Trigger $trigger, Request $request): Response
    {
        $secret = $trigger->signing_secret;
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $mode = $request->query('hub_mode', $request->query('hub.mode'));

        abort_unless($mode === 'subscribe' && is_string($challenge) && filled($secret), 404);
        abort_unless(hash_equals($secret, $token), 403);

        return response($challenge, 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * What a run receives as its input.
     *
     * A JSON body, a form and the query string arrive as fields. Anything else
     * (plain text, XML...) is kept whole under `body`, as is a JSON body that
     * didn't parse. The headers named in the node's "Pass headers" setting are
     * added under `_headers`, never the ones that carry credentials.
     *
     * @return array<string, mixed>
     */
    private function payloadFor(Trigger $trigger, Request $request, string $rawBody): array
    {
        $payload = (array) $request->all();

        if ($rawBody !== '' && ($payload === [] || ! ($request->isJson() || str_contains((string) $request->header('Content-Type'), 'form')))) {
            $payload['body'] = $rawBody;
        }

        $passed = collect((array) ($trigger->config['pass_headers'] ?? []))
            ->map(fn (mixed $name): string => strtolower(trim((string) $name)))
            ->reject(fn (string $name): bool => $name === '' || in_array($name, $this->credentialHeaders($trigger), true))
            ->mapWithKeys(fn (string $name): array => [$name => $request->header($name)])
            ->filter(fn (mixed $value): bool => $value !== null);

        if ($passed->isNotEmpty()) {
            $payload['_headers'] = $passed->all();
        }

        return $payload;
    }

    /**
     * Headers that carry the sender's credentials and so never reach a run,
     * whose input teammates can read.
     *
     * @return list<string>
     */
    private function credentialHeaders(Trigger $trigger): array
    {
        return [
            'authorization', 'cookie', 'proxy-authorization', 'x-signature-256', 'x-hub-signature-256',
            'stripe-signature', 'x-slack-signature',
            strtolower((string) ($trigger->config['header_name'] ?? WebhookAuthentication::DEFAULT_HEADER)),
        ];
    }

    /**
     * The reply for a webhook set to "Respond with the result": wait, up to
     * the node's timeout, for the run to finish and return its output.
     *
     * A run that is still going when the time is up answers 202 with its id, so
     * the caller can look it up. The wait holds this request open, so it is
     * capped by `triggers.max_sync_wait_seconds`.
     */
    private function respondWithResult(Trigger $trigger, TriggerEvent $event): Response
    {
        $timeout = max(1, min(
            (int) ($trigger->config['response_timeout_seconds'] ?? 10),
            (int) config('triggers.max_sync_wait_seconds'),
        ));
        $deadline = microtime(true) + $timeout;
        $run = null;

        while (true) {
            $event->refresh();
            $run = $event->run_id !== null ? Run::query()->find($event->run_id) : null;

            $settledWithoutRun = $run === null && in_array($event->status, [
                TriggerEventStatus::Ignored, TriggerEventStatus::Skipped, TriggerEventStatus::Failed, TriggerEventStatus::Rejected,
            ], true);

            if ($settledWithoutRun || $run?->status->isTerminal() || microtime(true) >= $deadline) {
                break;
            }

            usleep(200_000);
        }

        $body = ['event_id' => $event->id, 'run_id' => $run?->id];

        if ($run === null) {
            return response()->json(
                ['status' => $event->status === TriggerEventStatus::Queued || $event->status === TriggerEventStatus::Running ? 'queued' : $event->status->value, ...$body, 'reason' => $event->error],
                $event->status === TriggerEventStatus::Queued || $event->status === TriggerEventStatus::Running ? 202 : 200,
            );
        }

        if (! $run->status->isTerminal()) {
            return response()->json(['status' => 'running', ...$body], 202);
        }

        return response()->json(
            ['status' => $run->status->value, ...$body, 'output' => $run->output, 'error' => $run->error],
            $run->status->value === 'completed' ? 200 : 500,
        );
    }

    /**
     * Whether a rejection should speak of a token rather than a signature.
     */
    private function usesHeaderToken(Trigger $trigger): bool
    {
        return $trigger->preset_id === null
            && ($trigger->config['authentication'] ?? null) === WebhookAuthentication::HeaderToken->value;
    }

    /**
     * The id the same delivery always has, so a retry is recognised: a
     * provider's own header or field, else an `Idempotency-Key` or
     * `X-Request-Id` the sender chose.
     */
    private function deliveryId(Trigger $trigger, Request $request): ?string
    {
        $preset = $trigger->preset;

        if ($preset?->dedupe_header !== null) {
            $value = $request->header($preset->dedupe_header);

            if ($value !== null) {
                return $value;
            }
        }

        if ($preset?->dedupe_payload_path !== null) {
            $value = data_get($request->all(), $preset->dedupe_payload_path);

            if ($value !== null) {
                return (string) $value;
            }
        }

        // A sender that retries on a timeout sends the same key each time. A
        // key too long for the column is hashed, which still repeats exactly.
        $key = $request->header('Idempotency-Key') ?? $request->header('X-Request-Id');

        if (filled($key)) {
            return 'key:'.(strlen($key) <= 200 ? $key : hash('sha256', $key));
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function flattenHeaders(Request $request): array
    {
        return collect($request->headers->all())
            ->map(fn (array $values): string => $values[0] ?? '')
            ->all();
    }
}
