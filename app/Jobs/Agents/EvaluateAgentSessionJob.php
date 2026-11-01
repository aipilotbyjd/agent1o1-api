<?php

namespace App\Jobs\Agents;

use App\Enums\Queue;
use App\Listeners\Agents\ScheduleSessionEvaluation;
use App\Models\Agents\AgentSession;
use App\Services\Agents\SessionEvaluator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Runs `SessionEvaluator` for one `AgentSession`, dispatched with a delay by
 * `Listeners\Agents\ScheduleSessionEvaluation`.
 *
 * `$debounceToken` implements Gumloop's "short debounce so an active chat
 * isn't evaluated prematurely": every turn re-schedules this job with a
 * fresh token stored in cache under the session's key, so only the job
 * carrying the *last* token to be scheduled actually evaluates: any job
 * queued by an earlier turn in the same burst finds its token superseded and
 * exits without doing anything.
 */
class EvaluateAgentSessionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300];

    public function __construct(
        private readonly AgentSession $session,
        private readonly string $debounceToken,
    ) {
        $this->onQueue(Queue::AiAgent->value);
    }

    public function handle(SessionEvaluator $evaluator): void
    {
        if (Cache::get(ScheduleSessionEvaluation::cacheKey($this->session->id)) !== $this->debounceToken) {
            return;
        }

        $evaluator->evaluate($this->session);
    }
}
