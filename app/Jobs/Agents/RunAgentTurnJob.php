<?php

namespace App\Jobs\Agents;

use App\Enums\Queue;
use App\Enums\RunStatus;
use App\Models\Runs\Run;
use App\Services\Agents\AgentRunner;
use App\Services\Agents\AgentTurnBroadcaster;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Runs a chat turn the request only opened (`AgentRunner::beginTurn()`):
 * calls the model and streams the reply to the conversation's channel as it
 * is written: see `AgentTurnBroadcaster`. Sending a message never waits for
 * the reply, and an open chat follows along over Reverb.
 *
 * A turn that is no longer `running` (already picked up, or failed as stale
 * while this waited in the queue) is skipped rather than run twice.
 */
class RunAgentTurnJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public readonly string $runId)
    {
        $this->onQueue(Queue::AiAgent->value);
    }

    public function handle(AgentRunner $runner, AgentTurnBroadcaster $broadcaster): void
    {
        $run = Run::query()->find($this->runId);

        if ($run === null || $run->status !== RunStatus::Running) {
            return;
        }

        try {
            $turn = $runner->streamBegun($run);
        } catch (Throwable $e) {
            // `streamBegun()` has already failed the run; the chat still
            // needs to hear about it.
            $broadcaster->fail($run, $e);

            return;
        }

        $broadcaster->broadcast($turn);
    }

    /**
     * The worker was killed or timed out mid-turn: nothing else will close
     * the run out, and the chat would wait on it forever.
     */
    public function failed(Throwable $e): void
    {
        $run = Run::query()->find($this->runId);

        if ($run !== null && ! $run->status->isTerminal()) {
            app(AgentTurnBroadcaster::class)->fail($run, $e);
        }
    }
}
