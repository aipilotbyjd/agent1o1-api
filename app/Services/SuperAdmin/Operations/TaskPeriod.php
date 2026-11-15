<?php

namespace App\Services\SuperAdmin\Operations;

use App\Enums\Operations\TaskCadence;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The stretch of time a task belongs to: today, this ISO week, this month, or
 * for a setup task, forever. Ticking a task off counts for one period, and
 * the next period starts it fresh. Everything is UTC.
 */
final readonly class TaskPeriod
{
    public function __construct(public string $key, public CarbonImmutable $startsAt) {}

    public static function current(TaskCadence $cadence, ?CarbonInterface $now = null, ?CarbonInterface $createdAt = null): self
    {
        $now = CarbonImmutable::instance($now ?? now())->utc();

        return match ($cadence) {
            TaskCadence::Daily => new self($now->toDateString(), $now->startOfDay()),
            TaskCadence::Weekly => new self($now->format('o-\WW'), $now->startOfWeek(CarbonInterface::MONDAY)),
            TaskCadence::Monthly => new self($now->format('Y-m'), $now->startOfMonth()),
            TaskCadence::Once => new self('once', CarbonImmutable::instance($createdAt ?? $now)->utc()),
        };
    }

    public function dueAt(int $hours): CarbonImmutable
    {
        return $this->startsAt->addHours($hours);
    }
}
