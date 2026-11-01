<?php

namespace App\Services\Triggers\Push;

use App\Contracts\Triggers\ProviderIngress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * A Notion integration's webhook URL: checks Notion's signature, answers the
 * one-time verification request, and lists the events in a delivery.
 *
 * Notion signs the raw body with the integration's verification token
 * (`X-Notion-Signature: sha256=…`). Setting the webhook up starts with an
 * unsigned request that only carries that token, which is how the operator
 * learns it.
 */
class NotionIngress implements ProviderIngress
{
    /**
     * The provider key.
     */
    public function provider(): string
    {
        return 'notion';
    }

    /**
     * Whether Notion signed the request with our token. The unsigned
     * verification request is let through; it starts nothing.
     */
    public function verify(Request $request): bool
    {
        if ($this->isVerificationRequest($request)) {
            return true;
        }

        $token = (string) config('triggers.push.notion.verification_token');
        $signature = (string) $request->header('X-Notion-Signature');

        if ($token === '' || $signature === '') {
            return false;
        }

        return hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), $token), $signature);
    }

    /**
     * The verification request: answered, and its token logged for the
     * operator when it isn't set yet.
     */
    public function handshake(Request $request): array|Response|null
    {
        if (! $this->isVerificationRequest($request)) {
            return null;
        }

        if ((string) config('triggers.push.notion.verification_token') === '') {
            Log::info('Notion webhook verification token received. Set TRIGGERS_NOTION_VERIFICATION_TOKEN to it, then verify the webhook in Notion.', [
                'verification_token' => (string) $request->json('verification_token'),
            ]);
        }

        return ['ok' => true];
    }

    /**
     * The one event of a delivery, filed under its Notion workspace.
     */
    public function events(Request $request): array
    {
        $body = $request->json()->all();

        if (! filled($body['id'] ?? null) || ! filled($body['type'] ?? null)) {
            return [];
        }

        return [[
            'event_id' => (string) $body['id'],
            'event_type' => (string) $body['type'],
            'routing_key' => filled($body['workspace_id'] ?? null) ? (string) $body['workspace_id'] : null,
            'payload' => $body,
        ]];
    }

    private function isVerificationRequest(Request $request): bool
    {
        return is_string($request->json('verification_token')) && ! $request->json()->has('type');
    }
}
