<?php

namespace App\Services\Triggers\Push;

use App\Contracts\Triggers\ProviderIngress;
use App\Models\Triggers\ProviderWatch;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Notifications from a Google push channel (Drive, Calendar). They are plain
 * headers with no body: the channel id and the token we chose for it.
 */
class GoogleChannelIngress implements ProviderIngress
{
    public function __construct(private readonly string $provider) {}

    public function provider(): string
    {
        return $this->provider;
    }

    public function verify(Request $request): bool
    {
        $watch = ProviderWatch::query()
            ->where('provider', $this->provider)
            ->where('external_id', (string) $request->header('X-Goog-Channel-ID'))
            ->first();

        return $watch !== null
            && is_string($watch->secret)
            && hash_equals($watch->secret, (string) $request->header('X-Goog-Channel-Token'));
    }

    /**
     * The first message on a new channel is a `sync` that only says it is open.
     */
    public function handshake(Request $request): array|Response|null
    {
        return $request->header('X-Goog-Resource-State') === 'sync' ? ['ok' => true] : null;
    }

    public function events(Request $request): array
    {
        $channel = (string) $request->header('X-Goog-Channel-ID');
        $number = (string) $request->header('X-Goog-Message-Number');

        if ($channel === '' || $number === '') {
            return [];
        }

        return [[
            'event_id' => "{$channel}-{$number}",
            'event_type' => (string) $request->header('X-Goog-Resource-State', 'change'),
            'routing_key' => $channel,
            'payload' => ['resource_state' => $request->header('X-Goog-Resource-State'), 'message_number' => $number],
        ]];
    }
}
