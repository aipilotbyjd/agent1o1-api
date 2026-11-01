<?php

namespace App\Services\SuperAdmin\Alerts\Evaluators;

use App\Enums\Notifications\AlertSeverity;
use App\Models\SuperAdmin\AlertRule;
use App\Services\SuperAdmin\Alerts\AlertEvaluator;
use App\Services\SuperAdmin\Alerts\AlertSignal;
use App\Services\SuperAdmin\System\Checks\HealthCheck;
use App\Services\SuperAdmin\System\QueueInspector;

/**
 * A queue whose oldest waiting job has waited longer than the thresholds in
 * `config/super_admin_health.php` (the same ones System Health uses).
 */
class QueueBacklog implements AlertEvaluator
{
    public function __construct(private readonly QueueInspector $queues) {}

    public static function type(): string
    {
        return 'queue_backlog';
    }

    public static function defaults(): array
    {
        return [];
    }

    public function evaluate(AlertRule $rule): array
    {
        $signals = [];

        foreach ($this->queues->queues() as $queue) {
            if ($queue['status'] === HealthCheck::HEALTHY) {
                continue;
            }

            $signals[] = new AlertSignal(
                $queue['name'],
                sprintf('Queue "%s" has jobs waiting %d seconds (%d pending).', $queue['name'], $queue['oldest_wait_seconds'] ?? 0, $queue['pending']),
                ['queue' => $queue['name'], 'pending' => $queue['pending'], 'oldest_wait_seconds' => $queue['oldest_wait_seconds']],
                $queue['status'] === HealthCheck::DOWN ? AlertSeverity::Critical : null,
            );
        }

        return $signals;
    }
}
