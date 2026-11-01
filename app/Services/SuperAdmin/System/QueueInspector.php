<?php

namespace App\Services\SuperAdmin\System;

use App\Enums\Queue as QueueName;
use App\Services\SuperAdmin\System\Checks\HealthCheck;
use Illuminate\Queue\RedisQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Reads how full and how slow each queue is. The `database` driver is read
 * with plain queries; `redis` through the queue's own size methods. Any other
 * driver (sync, null) reports no queues.
 */
class QueueInspector
{
    public function driver(): string
    {
        return (string) config('queue.connections.'.config('queue.default').'.driver', config('queue.default'));
    }

    /**
     * @return list<array{name: string, pending: int, delayed: int, reserved: int, oldest_wait_seconds: ?int, failed_last_hour: int, status: string}>
     */
    public function queues(): array
    {
        $rows = match ($this->driver()) {
            'database' => $this->databaseQueues(),
            'redis' => $this->redisQueues(),
            default => [],
        };

        $failed = app(FailedJobService::class)->failedPerQueue(now()->subHour());

        $queues = [];

        foreach ($rows as $row) {
            $queues[] = $row + [
                'failed_last_hour' => (int) ($failed[$row['name']] ?? 0),
                'status' => $this->statusFor($row['name'], $row['oldest_wait_seconds']),
            ];
        }

        usort($queues, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $queues;
    }

    /**
     * @return list<array{name: string, pending: int, delayed: int, reserved: int, oldest_wait_seconds: ?int}>
     */
    private function databaseQueues(): array
    {
        $connection = config('queue.connections.'.config('queue.default'));
        $table = $connection['table'] ?? 'jobs';
        $now = now()->getTimestamp();

        $rows = DB::connection($connection['connection'] ?? null)->table($table)
            ->selectRaw('queue,
                sum(case when reserved_at is null and available_at <= ? then 1 else 0 end) as pending,
                sum(case when reserved_at is null and available_at > ? then 1 else 0 end) as delayed,
                sum(case when reserved_at is not null then 1 else 0 end) as reserved,
                min(case when reserved_at is null and available_at <= ? then available_at end) as oldest', [$now, $now, $now])
            ->groupBy('queue')
            ->get()
            ->keyBy('queue');

        $names = collect($this->knownQueues())->merge($rows->keys())->unique()->values();

        return $names->map(fn (string $name): array => [
            'name' => $name,
            'pending' => (int) ($rows[$name]->pending ?? 0),
            'delayed' => (int) ($rows[$name]->delayed ?? 0),
            'reserved' => (int) ($rows[$name]->reserved ?? 0),
            'oldest_wait_seconds' => isset($rows[$name]->oldest) ? max(0, $now - (int) $rows[$name]->oldest) : null,
        ])->all();
    }

    /**
     * @return list<array{name: string, pending: int, delayed: int, reserved: int, oldest_wait_seconds: ?int}>
     */
    private function redisQueues(): array
    {
        $connection = Queue::connection();

        return collect($this->knownQueues())->map(function (string $name) use ($connection): array {
            $oldest = $connection instanceof RedisQueue && method_exists($connection, 'creationTimeOfOldestPendingJob')
                ? $connection->creationTimeOfOldestPendingJob($name)
                : null;

            return [
                'name' => $name,
                'pending' => $connection instanceof RedisQueue ? (int) $connection->pendingSize($name) : (int) $connection->size($name),
                'delayed' => $connection instanceof RedisQueue ? (int) $connection->delayedSize($name) : 0,
                'reserved' => $connection instanceof RedisQueue ? (int) $connection->reservedSize($name) : 0,
                'oldest_wait_seconds' => $oldest === null ? null : max(0, now()->getTimestamp() - (int) $oldest),
            ];
        })->all();
    }

    /**
     * @return list<string>
     */
    private function knownQueues(): array
    {
        return ['default', ...array_map(fn (QueueName $queue): string => $queue->value, QueueName::cases())];
    }

    private function statusFor(string $queue, ?int $wait): string
    {
        if ($wait === null) {
            return HealthCheck::HEALTHY;
        }

        $override = config("super_admin_health.queues.overrides.{$queue}", []);
        $warning = $override['warning'] ?? config('super_admin_health.queues.wait_warning_seconds');
        $critical = $override['critical'] ?? config('super_admin_health.queues.wait_critical_seconds');

        return match (true) {
            $wait >= $critical => HealthCheck::DOWN,
            $wait >= $warning => HealthCheck::DEGRADED,
            default => HealthCheck::HEALTHY,
        };
    }
}
