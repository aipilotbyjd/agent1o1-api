<?php

namespace App\Services\SuperAdmin\System;

use App\Services\SuperAdmin\System\Checks\CacheCheck;
use App\Services\SuperAdmin\System\Checks\DatabaseCheck;
use App\Services\SuperAdmin\System\Checks\DiskCheck;
use App\Services\SuperAdmin\System\Checks\EnvironmentCheck;
use App\Services\SuperAdmin\System\Checks\HealthCheck;
use App\Services\SuperAdmin\System\Checks\HorizonCheck;
use App\Services\SuperAdmin\System\Checks\MailCheck;
use App\Services\SuperAdmin\System\Checks\QueueCheck;
use App\Services\SuperAdmin\System\Checks\RedisCheck;
use App\Services\SuperAdmin\System\Checks\ReverbCheck;
use App\Services\SuperAdmin\System\Checks\SchedulerCheck;
use App\Services\SuperAdmin\System\Checks\SetupDataCheck;
use App\Services\SuperAdmin\System\Checks\StorageCheck;
use App\Services\SuperAdmin\System\Checks\StripeCheck;
use App\Services\SuperAdmin\System\Checks\StuckWorkCheck;
use Throwable;

/**
 * Runs every health check and rolls them into one status. Checks are isolated:
 * one that throws or is slow is reported as a problem of its own and never
 * takes the others, or the endpoint, down with it.
 */
class HealthChecker
{
    /**
     * @var list<class-string<HealthCheck>>
     */
    public const CHECKS = [
        DatabaseCheck::class,
        CacheCheck::class,
        RedisCheck::class,
        QueueCheck::class,
        HorizonCheck::class,
        SchedulerCheck::class,
        ReverbCheck::class,
        MailCheck::class,
        StripeCheck::class,
        StorageCheck::class,
        DiskCheck::class,
        StuckWorkCheck::class,
        SetupDataCheck::class,
        EnvironmentCheck::class,
    ];

    /**
     * @return array{status: string, checked_at: string, checks: array<string, array{status: string, message: string, details: array<string, mixed>, latency_ms: int}>}
     */
    public function run(): array
    {
        $results = [];

        foreach (self::CHECKS as $class) {
            $check = app($class);
            $results[$check->name()] = $this->execute($check);
        }

        return [
            'status' => $this->overall($results),
            'checked_at' => now()->toIso8601String(),
            'checks' => $results,
        ];
    }

    /**
     * @return array{status: string, message: string, details: array<string, mixed>, latency_ms: int}
     */
    public function runOne(string $name): ?array
    {
        foreach (self::CHECKS as $class) {
            $check = app($class);

            if ($check->name() === $name) {
                return $this->execute($check);
            }
        }

        return null;
    }

    /**
     * @return array{status: string, message: string, details: array<string, mixed>, latency_ms: int}
     */
    private function execute(HealthCheck $check): array
    {
        $started = hrtime(true);

        try {
            $result = $check->run();
        } catch (Throwable $e) {
            $result = ['status' => HealthCheck::DOWN, 'message' => str($e->getMessage())->limit(200, '')->toString()];
        }

        $latency = (int) round((hrtime(true) - $started) / 1_000_000);
        $timeoutMs = (int) config('super_admin_health.check_timeout') * 1000;

        if ($latency > $timeoutMs && $result['status'] === HealthCheck::HEALTHY) {
            $result['status'] = HealthCheck::DEGRADED;
            $result['message'] .= " It took {$latency} ms to answer.";
        }

        return [
            'status' => $result['status'],
            'message' => $result['message'],
            'details' => $result['details'] ?? [],
            'latency_ms' => $latency,
        ];
    }

    /**
     * @param  array<string, array{status: string}>  $results
     */
    private function overall(array $results): string
    {
        $statuses = array_column($results, 'status');

        return match (true) {
            in_array(HealthCheck::DOWN, $statuses, true) => HealthCheck::DOWN,
            in_array(HealthCheck::DEGRADED, $statuses, true) => HealthCheck::DEGRADED,
            default => HealthCheck::HEALTHY,
        };
    }
}
