<?php

namespace App\Jobs\System;

use App\Enums\NodeRunStatus;
use App\Enums\Queue;
use App\Models\Runs\NodeRun;
use App\Services\Workflows\WorkflowRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Scheduled every minute (`routes/console.php`): the durability net for a
 * `Wait` node whose `callback_expires_at` has passed with no callback
 * received, mirroring `RetryStuckEventsCommand`'s recovery pattern for
 * trigger events (docs/TRIGGERS_PLAN.md).
 */
class ExpireStaleWaitsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue(Queue::Maintenance->value);
    }

    public function handle(WorkflowRunner $runner): void
    {
        NodeRun::query()
            ->where('status', NodeRunStatus::AwaitingCallback)
            ->whereNotNull('callback_expires_at')
            ->where('callback_expires_at', '<', now())
            ->each(fn (NodeRun $nodeRun) => $runner->expireWait($nodeRun));
    }
}
