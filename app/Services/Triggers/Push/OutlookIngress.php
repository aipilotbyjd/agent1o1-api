<?php

namespace App\Services\Triggers\Push;

use App\Contracts\Triggers\ProviderIngress;
use App\Models\Triggers\ProviderWatch;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Microsoft Graph change notifications.
 *
 * Graph first checks the URL by posting a `validationToken` that must be
 * echoed as plain text. After that every notification carries the
 * `clientState` secret we gave the subscription, which is checked against the
 * watch the notification names.
 */
class OutlookIngress implements ProviderIngress
{
    public function provider(): string
    {
        return 'outlook';
    }

    public function verify(Request $request): bool
    {
        if ($request->query('validationToken') !== null) {
            return true;
        }

        $notifications = $this->notifications($request);

        if ($notifications === []) {
            return false;
        }

        foreach ($notifications as $notification) {
            $watch = ProviderWatch::query()
                ->where('provider', 'outlook')
                ->where('external_id', (string) ($notification['subscriptionId'] ?? ''))
                ->first();

            if ($watch === null || ! is_string($watch->secret) || ! hash_equals($watch->secret, (string) ($notification['clientState'] ?? ''))) {
                return false;
            }
        }

        return true;
    }

    public function handshake(Request $request): array|Response|null
    {
        $token = $request->query('validationToken');

        return $token === null ? null : response((string) $token, 200, ['Content-Type' => 'text/plain']);
    }

    public function events(Request $request): array
    {
        $events = [];

        foreach ($this->notifications($request) as $notification) {
            $events[] = [
                'event_id' => sha1((string) json_encode($notification)),
                'event_type' => isset($notification['lifecycleEvent']) ? 'lifecycle' : 'message_created',
                'routing_key' => (string) $notification['subscriptionId'],
                'payload' => array_intersect_key($notification, array_flip(['subscriptionId', 'changeType', 'lifecycleEvent', 'resource'])),
            ];
        }

        return $events;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function notifications(Request $request): array
    {
        return array_values(array_filter(
            (array) $request->json('value', []),
            fn (mixed $item): bool => is_array($item) && filled($item['subscriptionId'] ?? null),
        ));
    }
}
