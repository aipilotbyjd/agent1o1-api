<?php

namespace App\Services\Triggers\Push;

use App\Contracts\Triggers\ProviderIngress;
use App\Models\Triggers\ProviderWatch;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Repository webhooks. Each is signed with the secret of the watch it came
 * from (`X-Hub-Signature-256`), found by the hook id and repository.
 */
class GitHubIngress implements ProviderIngress
{
    public function provider(): string
    {
        return 'github';
    }

    public function verify(Request $request): bool
    {
        $watch = $this->watchFor($request);
        $signature = (string) $request->header('X-Hub-Signature-256');

        return $watch !== null
            && is_string($watch->secret)
            && $signature !== ''
            && hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), $watch->secret), $signature);
    }

    /**
     * GitHub's `ping` when a hook is created.
     */
    public function handshake(Request $request): array|Response|null
    {
        return $request->header('X-GitHub-Event') === 'ping' ? ['ok' => true] : null;
    }

    public function events(Request $request): array
    {
        $delivery = (string) $request->header('X-GitHub-Delivery');
        $repo = strtolower((string) $request->json('repository.full_name'));

        if ($delivery === '' || $repo === '') {
            return [];
        }

        return [[
            'event_id' => $delivery,
            'event_type' => (string) $request->header('X-GitHub-Event'),
            'routing_key' => $repo.'#'.(string) $request->header('X-GitHub-Hook-ID'),
            'payload' => ['event' => $request->header('X-GitHub-Event'), 'action' => $request->json('action'), 'repository' => $repo],
        ]];
    }

    private function watchFor(Request $request): ?ProviderWatch
    {
        $repo = strtolower((string) $request->json('repository.full_name'));

        return ProviderWatch::query()
            ->where('provider', 'github')
            ->where('external_id', (string) $request->header('X-GitHub-Hook-ID'))
            ->get()
            ->first(fn (ProviderWatch $watch): bool => ($watch->meta['repo'] ?? null) === $repo);
    }
}
