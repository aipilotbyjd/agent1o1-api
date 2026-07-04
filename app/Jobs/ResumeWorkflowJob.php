<?php

namespace App\Jobs;

use App\Engine\WorkflowRunner;
use App\Models\Execution;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ResumeWorkflowJob implements ShouldQueue
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

        if (! $execution || ! $execution->isWaiting()) {
            return;
        }

        $runner->resume($execution);
    }
}
