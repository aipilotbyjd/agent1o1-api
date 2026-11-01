<?php

namespace App\Jobs\System;

use App\Enums\Queue;
use App\Enums\RunStatus;
use App\Models\Runs\Run;
use App\Services\Agents\AgentRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use RuntimeException;

/**
 * Scheduled every five minutes (`routes/console.php`): the durability net
 * for an agent turn whose process died mid-reply: a web request killed by a
 * time limit or a restart, or a worker lost while running a subagent or a
 * queued chat turn (`RunAgentTurnJob`) whose `failed()` never got to run. None of
 * those can run the code that closes the turn out, so its `Run` would stay
 * `running` forever. Once a run has been going far longer than any turn can
 * (`AllowLongAgentTurn::SECONDS` for one in a request, less for a subagent
 * job), it is failed the same way a turn that threw is.
 */
class FailStuckAgentTurnsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const STUCK_AFTER_MINUTES = 15;

    public function __construct()
    {
        $this->onQueue(Queue::Maintenance->value);
    }

    public function handle(AgentRunner $runner): void
    {
        Run::query()
            ->where('runnable_type', 'agent_session')
            ->where('status', RunStatus::Running->value)
            ->where('started_at', '<', now()->subMinutes(self::STUCK_AFTER_MINUTES))
            ->get()
            ->each(fn (Run $run) => $runner->failTurn($run, new RuntimeException('The agent stopped before finishing its reply.')));
    }
}
