<?php

namespace App\Services\SuperAdmin\System\Checks;

use App\Services\SuperAdmin\System\StuckWorkDetector;

class StuckWorkCheck implements HealthCheck
{
    public function __construct(private readonly StuckWorkDetector $stuck) {}

    public function name(): string
    {
        return 'stuck_work';
    }

    public function run(): array
    {
        $summary = $this->stuck->report();
        $total = $summary['total'];

        $status = match (true) {
            $total >= config('super_admin_health.stuck.critical_count') => self::DOWN,
            $total >= config('super_admin_health.stuck.warning_count') => self::DEGRADED,
            default => self::HEALTHY,
        };

        return [
            'status' => $status,
            'message' => $total === 0 ? 'Nothing is stuck.' : "{$total} item(s) look stuck.",
            'details' => array_map(fn (array $group): int => $group['count'], $summary['groups']),
        ];
    }
}
