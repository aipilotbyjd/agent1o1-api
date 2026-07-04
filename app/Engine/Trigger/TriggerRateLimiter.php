<?php

namespace App\Engine\Trigger;

use App\Models\Trigger;
use Illuminate\Support\Facades\RateLimiter;

class TriggerRateLimiter
{
    public function attempt(Trigger $trigger): bool
    {
        if (! $trigger->rate_limit_count || ! $trigger->rate_limit_window) {
            return true;
        }

        return RateLimiter::attempt(
            key: "trigger:{$trigger->id}",
            maxAttempts: $trigger->rate_limit_count,
            callback: fn () => true,
            decaySeconds: $trigger->rate_limit_window,
        ) !== false;
    }

    public function availableIn(Trigger $trigger): int
    {
        return RateLimiter::availableIn("trigger:{$trigger->id}");
    }
}
