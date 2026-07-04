<?php

namespace App\Jobs;

use App\Engine\WorkflowRunner;
use App\Enums\ExecutionStatus;
use App\Models\Execution;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExecuteWorkflowJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(public readonly string $executionId)
    {
        $this->onQueue('engine');
    }

    public function handle(WorkflowRunner $runner): void
    {
        $execution = Execution::find($this->executionId);

        if (! $execution || ! $execution->isPending()) {
            return;
        }

        $runner->run($execution);
    }

    public function failed(?\Throwable $exception): void
    {
        Execution::where('id', $this->executionId)
            ->whereIn('status', [ExecutionStatus::Pending, ExecutionStatus::Running])
            ->update([
                'status' => ExecutionStatus::Failed,
                'finished_at' => now(),
                'error' => ['message' => $exception?->getMessage() ?? 'Job failed'],
            ]);
    }
}
