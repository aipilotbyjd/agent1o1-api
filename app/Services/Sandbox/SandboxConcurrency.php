<?php

namespace App\Services\Sandbox;

use App\Models\Assistant\AssistantSandbox;
use App\Models\Sandbox\SandboxProvider;
use Illuminate\Support\Facades\Cache;

/**
 * Keeps the platform inside what each provider account allows at once
 * (`max_concurrent`). Counts the conversation computers still alive plus
 * the one-shot runs in flight; a run's slot expires on its own so a
 * crashed worker cannot hold a provider shut.
 */
class SandboxConcurrency
{
    public function hasRoom(SandboxProvider $provider): bool
    {
        return $provider->max_concurrent === null || $this->inUse($provider) < $provider->max_concurrent;
    }

    public function inUse(SandboxProvider $provider): int
    {
        return $this->persistent($provider) + $this->inFlight($provider);
    }

    /**
     * Takes a slot for a one-shot run. False when the provider is full.
     */
    public function enter(SandboxProvider $provider): bool
    {
        $key = $this->key($provider);

        Cache::add($key, 0, (int) config('sandbox.slot_ttl_seconds'));
        $now = (int) Cache::increment($key);

        if ($provider->max_concurrent !== null && $this->persistent($provider) + $now > $provider->max_concurrent) {
            Cache::decrement($key);

            return false;
        }

        return true;
    }

    public function leave(SandboxProvider $provider): void
    {
        $key = $this->key($provider);

        if ((int) Cache::get($key, 0) > 0) {
            Cache::decrement($key);
        }
    }

    private function inFlight(SandboxProvider $provider): int
    {
        return max(0, (int) Cache::get($this->key($provider), 0));
    }

    private function persistent(SandboxProvider $provider): int
    {
        return AssistantSandbox::query()
            ->where('provider', $provider->key)
            ->where('last_used_at', '>=', now()->subSeconds((int) config('assistant.sandbox.idle_seconds')))
            ->count();
    }

    private function key(SandboxProvider $provider): string
    {
        return "sandbox:inflight:{$provider->key}";
    }
}
