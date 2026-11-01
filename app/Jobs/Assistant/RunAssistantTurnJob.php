<?php

namespace App\Jobs\Assistant;

use App\Models\Assistant\AssistantSession;
use App\Models\Assistant\AssistantTurn;
use App\Services\Assistant\Channels\ChannelReplies;
use App\Services\Assistant\Runtime\AssistantLoop;
use App\Services\Assistant\Runtime\ContextCompactor;
use App\Services\Assistant\Runtime\TurnRunner;
use App\Services\Assistant\Triggers\TriggerOutcomes;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Runs one assistant turn on its own long-running queue (see the
 * `redis-assistant` connection), then starts the next message the owner
 * queued while it ran. Never retried: `TurnRunner` settles every failure
 * itself, and a turn is claimed atomically so a duplicate is a no-op.
 */
class RunAssistantTurnJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout;

    public function __construct(public AssistantTurn $turn)
    {
        $this->timeout = (int) config('assistant.runtime.job_timeout');
        $this->onConnection(config('assistant.runtime.queue_connection'));
        $this->onQueue(config('assistant.runtime.queue'));
    }

    public function handle(TurnRunner $runner, AssistantLoop $loop, ContextCompactor $compactor, TriggerOutcomes $outcomes, ChannelReplies $replies): void
    {
        $runner->run($this->turn);

        $turn = $this->turn->refresh();
        $outcomes->record($turn);
        $replies->deliver($turn);

        $session = $this->turn->session()->firstOrFail();

        $this->compactIfNeeded($compactor, $session);

        $loop->startNextQueued($session);
    }

    /**
     * Between turns, so the next one starts on the shorter context. A
     * failed summary only means the next turn sends a longer history.
     */
    private function compactIfNeeded(ContextCompactor $compactor, AssistantSession $session): void
    {
        try {
            if ($compactor->needsCompaction($session)) {
                $compactor->compact($session);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
