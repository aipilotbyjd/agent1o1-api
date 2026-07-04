<?php

namespace App\Engine;

use App\Enums\ExecutionNodeStatus;

readonly class NodeResult
{
    public function __construct(
        public ExecutionNodeStatus $status,
        public ?array $output = null,
        public ?array $error = null,
        public int $durationMs = 0,
        public ?array $activeBranches = null,
        public ?array $loopItems = null,
    ) {}

    public static function completed(array $output, int $durationMs = 0): self
    {
        return new self(
            status: ExecutionNodeStatus::Completed,
            output: $output,
            durationMs: $durationMs,
        );
    }

    public static function failed(string $message, ?string $code = null): self
    {
        return new self(
            status: ExecutionNodeStatus::Failed,
            error: ['message' => $message, 'code' => $code],
        );
    }

    public static function skipped(string $reason = 'Branch not active'): self
    {
        return new self(
            status: ExecutionNodeStatus::Skipped,
            output: [],
            error: ['reason' => $reason],
        );
    }

    public static function errorOutput(array $errorData): self
    {
        return new self(
            status: ExecutionNodeStatus::Failed,
            output: $errorData,
            error: $errorData,
        );
    }

    public static function withBranches(array $output, array $activeBranches, int $durationMs = 0): self
    {
        return new self(
            status: ExecutionNodeStatus::Completed,
            output: $output,
            durationMs: $durationMs,
            activeBranches: $activeBranches,
        );
    }

    public static function withLoopItems(array $loopItems, int $durationMs = 0): self
    {
        return new self(
            status: ExecutionNodeStatus::Completed,
            output: ['items' => $loopItems],
            durationMs: $durationMs,
            loopItems: $loopItems,
        );
    }

    public function isSuccess(): bool
    {
        return $this->status === ExecutionNodeStatus::Completed;
    }

    public function isFailed(): bool
    {
        return $this->status === ExecutionNodeStatus::Failed;
    }

    public function isSkipped(): bool
    {
        return $this->status === ExecutionNodeStatus::Skipped;
    }
}
