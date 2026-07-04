<?php

namespace App\Jobs;

use App\Engine\Polling\PollingRegistry;
use App\Models\Trigger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PollSingleTriggerJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public readonly string $triggerId)
    {
        $this->onQueue('triggers');
    }

    public function handle(PollingRegistry $registry): void
    {
        $trigger = Trigger::find($this->triggerId);

        if (! $trigger || ! $trigger->is_active || $trigger->is_paused) {
            return;
        }

        $executor = $registry->executorFor($trigger);
        $executor?->execute($trigger);
    }
}
