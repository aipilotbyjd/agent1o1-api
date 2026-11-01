<?php

namespace App\Services\Triggers\Push;

use App\Contracts\Triggers\ProviderIngress;
use App\Models\Triggers\ProviderWatch;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Jira webhooks. Jira does not sign them, so the URL each watch registered has
 * a random token (`?token=`), found by its hash. The token is the whole check,
 * and a delivery only makes the trigger's poll look now: it is never trusted
 * for what it says.
 */
class JiraIngress implements ProviderIngress
{
    public function provider(): string
    {
        return 'jira';
    }

    public function verify(Request $request): bool
    {
        $token = (string) $request->query('token');

        return $token !== '' && ProviderWatch::query()->where('provider', 'jira')->where('meta->token_hash', hash('sha256', $token))->exists();
    }

    public function handshake(Request $request): array|Response|null
    {
        return null;
    }

    public function events(Request $request): array
    {
        $event = (string) $request->json('webhookEvent');

        if ($event === '') {
            return [];
        }

        return [[
            'event_id' => (string) ($request->header('X-Atlassian-Webhook-Identifier') ?: sha1($request->getContent())),
            'event_type' => $event,
            'routing_key' => hash('sha256', (string) $request->query('token')),
            'payload' => ['event' => $event, 'issue' => $request->json('issue.key')],
        ]];
    }
}
