<?php

namespace App\Services\SuperAdmin\Operations;

use App\Enums\Operations\TaskStatus;

/**
 * What an automatic check found: all clear, look at this, or act now, with a
 * plain sentence saying why.
 */
final readonly class CheckResult
{
    public function __construct(public TaskStatus $status, public string $message) {}

    public static function ok(string $message): self
    {
        return new self(TaskStatus::Done, $message);
    }

    public static function warn(string $message): self
    {
        return new self(TaskStatus::Due, $message);
    }

    public static function fail(string $message): self
    {
        return new self(TaskStatus::Overdue, $message);
    }
}
