<?php

namespace App\Services\SuperAdmin\Operations;

use App\Enums\Operations\TaskStatus;
use App\Models\Operations\OperationsTask;
use App\Models\Operations\OperationsTaskEntry;
use Carbon\CarbonImmutable;

/**
 * A task as it stands right now: its status, why, and who did what this period.
 */
final readonly class TaskState
{
    public function __construct(
        public OperationsTask $task,
        public TaskStatus $status,
        public ?string $detail,
        public ?string $period,
        public ?CarbonImmutable $dueAt,
        public ?OperationsTaskEntry $done,
        public ?OperationsTaskEntry $snoozed,
        public ?CarbonImmutable $lastDoneAt,
    ) {}
}
