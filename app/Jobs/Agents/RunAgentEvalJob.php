<?php

namespace App\Jobs\Agents;

use App\Enums\Queue;
use App\Models\Agents\AgentEvalRun;
use App\Services\Agents\EvalRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Executes a pending `AgentEvalRun` queued by `EvalRunner::start()`. One
 * attempt only: cases call the real model, so a retry would pay for (and
 * bill) the suite twice: `EvalRunner::execute()` refuses a non-pending run
 * for the same reason.
 */
class RunAgentEvalJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * Kept under the `ai-agent` supervisor's own timeout (config/horizon.php).
     */
    public int $timeout = 300;

    public function __construct(public readonly string $evalRunId)
    {
        $this->onQueue(Queue::AiAgent->value);
    }

    public function handle(EvalRunner $runner): void
    {
        $evalRun = AgentEvalRun::find($this->evalRunId);

        if ($evalRun !== null) {
            $runner->execute($evalRun);
        }
    }

    /**
     * The worker died mid-suite (timeout, crash): `execute()`'s own catch
     * never ran, so close the run out here.
     */
    public function failed(Throwable $e): void
    {
        $evalRun = AgentEvalRun::find($this->evalRunId);

        if ($evalRun !== null) {
            app(EvalRunner::class)->fail($evalRun, $e);
        }
    }
}
