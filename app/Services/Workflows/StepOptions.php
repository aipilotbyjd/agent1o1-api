<?php

namespace App\Services\Workflows;

/**
 * Per-node execution knobs, parsed once from a node's `config._options`:
 * namespaced under `_options` so it never collides with the node's own
 * `configSchema()` fields. A per-step timeout is deliberately absent (a
 * running node can't be interrupted); `GraphValidator` rejects it. See
 * docs/WORKFLOWS_PLAN.md's `StepOptions` entry.
 */
final class StepOptions
{
    public function __construct(
        public readonly int $maxAttempts = 1,
        public readonly int $retryDelaySeconds = 0,
        public readonly bool $continueOnError = false,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromNodeConfig(array $config): self
    {
        $options = $config['_options'] ?? [];

        return new self(
            maxAttempts: max(1, (int) ($options['max_attempts'] ?? 1)),
            retryDelaySeconds: max(0, (int) ($options['retry_delay_seconds'] ?? 0)),
            continueOnError: (bool) ($options['continue_on_error'] ?? false),
        );
    }
}
