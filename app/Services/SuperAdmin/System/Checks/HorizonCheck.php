<?php

namespace App\Services\SuperAdmin\System\Checks;

use App\Services\SuperAdmin\System\HorizonInspector;

class HorizonCheck implements HealthCheck
{
    public function __construct(private readonly HorizonInspector $horizon) {}

    public function name(): string
    {
        return 'horizon';
    }

    public function run(): array
    {
        $summary = $this->horizon->summary();

        if (! $summary['applicable']) {
            return ['status' => self::SKIPPED, 'message' => 'Horizon only runs on a Redis queue.'];
        }

        return match ($summary['status']) {
            'running' => ['status' => self::HEALTHY, 'message' => 'Horizon is running.', 'details' => $summary],
            'paused' => ['status' => self::DEGRADED, 'message' => 'Horizon is paused: no jobs are being processed.', 'details' => $summary],
            default => ['status' => self::DOWN, 'message' => 'Horizon is not running.', 'details' => $summary],
        };
    }
}
