<?php

namespace App\Jobs\Agents;

use App\Enums\Queue;
use App\Enums\RunStatus;
use App\Exceptions\RunStateException;
use App\Models\Runs\Run;
use App\Services\Agents\AgentRunner;
use App\Services\Agents\AgentTurnBroadcaster;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Continues a chat turn whose waiting actions have all been decided: see
 * `AgentRunner::resumeStream()`. Whoever decided may have long since left,
 * and a triggered turn never had anyone watching, so this runs on the queue;
 * an open chat follows along through the conversation's broadcasts, the
 * reply arriving as it is written (`AgentTurnBroadcaster`).
 *
 * A turn someone already resumed (a second decision landing at once) is
 * skipped rather than retried.
 */
class ResumeAgentTurnJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public readonly string $runId)
    {
        $this->onQueue(Queue::AiAgent->value);
    }

    public function handle(AgentRunner $runner, AgentTurnBroadcaster $broadcaster): void
    {
        $run = Run::query()->find($this->runId);

        if ($run === null) {
            return;
        }

        try {
            $turn = $runner->resumeStream($run);
        } catch (RunStateException) {
            // Already resumed, or still waiting on a decision.
            return;
        }

        $broadcaster->broadcast($turn);
    }

    /**
     * Resuming blew up, or the worker was killed mid-turn: close the run
     * out and tell the chat, which would otherwise wait on it forever.
     */
    public function failed(Throwable $e): void
    {
        $run = Run::query()->find($this->runId);

        if ($run !== null && ! $run->status->isTerminal() && $run->status !== RunStatus::AwaitingApproval) {
            app(AgentTurnBroadcaster::class)->fail($run, $e);
        }
    }
}
