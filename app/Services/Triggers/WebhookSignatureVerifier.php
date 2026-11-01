<?php

namespace App\Services\Triggers;

use App\Enums\Triggers\WebhookAuthentication;
use App\Models\Triggers\Trigger;
use Illuminate\Http\Request;

/**
 * Checks that a webhook request is genuine.
 *
 * In order of precedence:
 * - A trigger made from a provider preset uses that provider's scheme
 *   (GitHub, Stripe, Slack).
 * - A Webhook trigger node uses its chosen authentication: none, a header
 *   token, or the generic signature.
 * - Any other trigger is open, unless its owner set a secret, which is then
 *   enforced with the generic signature.
 *
 * Signatures are checked against the raw request body, never the decoded
 * payload: providers sign exact bytes, and re-encoding the JSON would not
 * reproduce them.
 */
class WebhookSignatureVerifier
{
    private const TIMESTAMP_TOLERANCE_SECONDS = 300;

    /**
     * Whether the request passes the trigger's check.
     */
    public function verify(Trigger $trigger, Request $request, string $rawBody): bool
    {
        $scheme = $trigger->preset?->signature_scheme;
        $secret = $trigger->signing_secret;

        // A Webhook trigger node names its method. One that needs a secret
        // rejects everything until it's set, rather than accepting anyone.
        $authentication = WebhookAuthentication::tryFrom((string) ($trigger->config['authentication'] ?? ''));

        if ($scheme === null && $authentication !== null) {
            return match ($authentication) {
                WebhookAuthentication::None => true,
                WebhookAuthentication::HeaderToken => filled($secret) && $this->verifyHeaderToken($trigger, $request, $secret),
                WebhookAuthentication::Signature => filled($secret) && $this->verifyGeneric($request, $rawBody, $secret),
            };
        }

        // No preset and no secret: open. A secret without a preset is
        // enforced with the generic signature, never silently ignored.
        if ($scheme === null && blank($secret)) {
            return true;
        }

        if (blank($secret)) {
            return false;
        }

        return match ($scheme ?? 'generic') {
            'generic' => $this->verifyGeneric($request, $rawBody, $secret),
            'github' => $this->verifyGithub($request, $rawBody, $secret),
            'stripe' => $this->verifyStripe($request, $rawBody, $secret),
            'slack' => $this->verifySlack($request, $rawBody, $secret),
            default => false,
        };
    }

    /**
     * The secret sent as-is in a header (`X-Webhook-Token` unless the node
     * names another), compared in constant time.
     */
    private function verifyHeaderToken(Trigger $trigger, Request $request, string $secret): bool
    {
        $header = $trigger->config['header_name'] ?? null;
        $token = (string) $request->header(filled($header) ? (string) $header : WebhookAuthentication::DEFAULT_HEADER);

        return $token !== '' && hash_equals($secret, $token);
    }

    /**
     * The generic signature: `X-Signature-256: sha256=<hex>`, an HMAC-SHA256
     * of the raw body keyed with the trigger's secret.
     */
    private function verifyGeneric(Request $request, string $rawBody, string $secret): bool
    {
        $signature = (string) $request->header('X-Signature-256');

        if ($signature === '') {
            return false;
        }

        return hash_equals('sha256='.hash_hmac('sha256', $rawBody, $secret), $signature);
    }

    /**
     * GitHub's `X-Hub-Signature-256`: an HMAC-SHA256 of the raw body.
     */
    private function verifyGithub(Request $request, string $rawBody, string $secret): bool
    {
        $signature = (string) $request->header('X-Hub-Signature-256');

        if ($signature === '') {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Stripe's `Stripe-Signature`: an HMAC-SHA256 of `timestamp.body`, with a
     * timestamp no older than the tolerance (replay protection).
     */
    private function verifyStripe(Request $request, string $rawBody, string $secret): bool
    {
        $parts = $this->parseHeaderPairs((string) $request->header('Stripe-Signature'));
        $timestamp = $parts['t'][0] ?? null;
        $signatures = $parts['v1'] ?? [];

        if ($timestamp === null || $signatures === [] || $this->isStale((int) $timestamp)) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$rawBody}", $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, (string) $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Slack's `X-Slack-Signature`: an HMAC-SHA256 of `v0:timestamp:body`, with
     * a timestamp no older than the tolerance (replay protection).
     */
    private function verifySlack(Request $request, string $rawBody, string $secret): bool
    {
        $timestamp = (string) $request->header('X-Slack-Request-Timestamp');
        $signature = (string) $request->header('X-Slack-Signature');

        if ($timestamp === '' || $signature === '' || $this->isStale((int) $timestamp)) {
            return false;
        }

        $expected = 'v0='.hash_hmac('sha256', "v0:{$timestamp}:{$rawBody}", $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Split a `key=value,key=value` header (Stripe-Signature's shape) into
     * `key => [values]`. A key can repeat: Stripe sends several `v1` values
     * while a secret is being rotated.
     *
     * @return array<string, array<int, string>>
     */
    private function parseHeaderPairs(string $header): array
    {
        $parts = [];

        foreach (explode(',', $header) as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, null);

            if ($key !== null && $value !== null) {
                $parts[$key][] = $value;
            }
        }

        return $parts;
    }

    /**
     * Whether a signed timestamp is too old to accept.
     */
    private function isStale(int $timestamp): bool
    {
        return abs(time() - $timestamp) > self::TIMESTAMP_TOLERANCE_SECONDS;
    }
}
