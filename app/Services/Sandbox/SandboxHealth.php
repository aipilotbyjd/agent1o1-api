<?php

namespace App\Services\Sandbox;

use App\Models\Sandbox\SandboxProvider;
use Illuminate\Support\Str;
use Throwable;

/**
 * The circuit breaker. A provider that fails several calls in a row is
 * left out of routing for a cooldown, then tried again by the next call; a
 * success closes the circuit. Only failures of the provider itself count:
 * a program that crashes is the user's, not the provider's.
 */
class SandboxHealth
{
    public function succeeded(SandboxProvider $provider): void
    {
        if (! $provider->exists || ($provider->consecutive_failures === 0 && $provider->circuit_open_until === null)) {
            return;
        }

        $provider->forceFill(['consecutive_failures' => 0, 'circuit_open_until' => null, 'last_error' => null])->save();
    }

    public function failed(SandboxProvider $provider, Throwable $error): void
    {
        if (! $provider->exists) {
            return;
        }

        $failures = $provider->consecutive_failures + 1;

        $provider->forceFill([
            'consecutive_failures' => $failures,
            'circuit_open_until' => $failures >= (int) config('sandbox.circuit.failures')
                ? now()->addSeconds((int) config('sandbox.circuit.cooldown_seconds'))
                : $provider->circuit_open_until,
            'last_error' => Str::limit($error->getMessage(), 500, ''),
        ])->save();
    }
}
