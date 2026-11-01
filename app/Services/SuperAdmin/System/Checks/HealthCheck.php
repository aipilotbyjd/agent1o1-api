<?php

namespace App\Services\SuperAdmin\System\Checks;

/**
 * One probe of one part of the platform. Implementations must be cheap and
 * must not throw: `HealthChecker` also guards them, but a check that reports
 * its own failure gives a better message.
 */
interface HealthCheck
{
    public const HEALTHY = 'healthy';

    public const DEGRADED = 'degraded';

    public const DOWN = 'down';

    /** The check was not applicable here (for example Horizon on a database queue). */
    public const SKIPPED = 'skipped';

    public function name(): string;

    /**
     * @return array{status: string, message: string, details?: array<string, mixed>}
     */
    public function run(): array;
}
