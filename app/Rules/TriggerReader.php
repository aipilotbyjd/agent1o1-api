<?php

namespace App\Rules;

use App\Services\Triggers\TriggerNodes;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A polling trigger's `config.node_type`: an app reader that can act as a
 * trigger (Gmail: New email, Slack: New message…). PollTrigger would only
 * find out on the first poll, as a failure toward the circuit breaker.
 */
class TriggerReader implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! app(TriggerNodes::class)->supportsTriggerMode($value)) {
            $fail('That app event can’t start a trigger.');
        }
    }
}
