<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Keeps one tenant from using all of a server the operator runs. An
 * endpoint may carry two limits, both calls per minute: one per workspace and
 * one for everyone together. Each call that is about to be made counts against
 * both; one that would pass either is refused, and refusing it costs nothing
 * (a call turned away by the total does not use up the workspace's allowance).
 *
 * Counted where a call's providers are chosen (`ByokProviderRegistrar`), which
 * every model call passes through. Only the operator's own endpoints are
 * limited: the usual providers have their own limits and a workspace's own key
 * is not the operator's capacity. The window is a fixed minute; the counters
 * live in the cache, so every server and worker shares them.
 */
class EndpointRateLimiter
{
    private const int WINDOW_SECONDS = 60;

    public function __construct(private readonly OperatorEndpoints $endpoints) {}

    /**
     * Counts a call to `$provider` for `$workspaceId`, if it is allowed.
     *
     * @return int|null null when the call may go ahead; otherwise how many
     *                  seconds until it could
     */
    public function attempt(string $provider, string $workspaceId): ?int
    {
        $limits = $this->endpoints->limitsFor($provider);

        if ($limits === null) {
            return null;
        }

        $buckets = array_filter([
            $limits['workspace'] === null ? null : [$this->key($provider, $workspaceId), $limits['workspace']],
            $limits['total'] === null ? null : [$this->key($provider), $limits['total']],
        ]);

        foreach ($buckets as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $this->noteRejected($provider);

                return max(1, RateLimiter::availableIn($key));
            }
        }

        foreach ($buckets as [$key]) {
            RateLimiter::hit($key, self::WINDOW_SECONDS);
        }

        return null;
    }

    /**
     * How many calls the limits have turned away today, to tell whether a limit
     * is too tight.
     */
    public function rejectedToday(string $provider): int
    {
        return (int) Cache::get($this->rejectedKey($provider), 0);
    }

    private function noteRejected(string $provider): void
    {
        $key = $this->rejectedKey($provider);

        Cache::add($key, 0, now()->endOfDay());
        Cache::increment($key);
    }

    private function key(string $provider, ?string $workspaceId = null): string
    {
        return "endpoint-limit:{$provider}:".($workspaceId ?? 'total');
    }

    private function rejectedKey(string $provider): string
    {
        return 'endpoint-rejected:'.today()->toDateString().":{$provider}";
    }
}
