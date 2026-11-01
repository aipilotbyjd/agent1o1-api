<?php

namespace App\Jobs\Agents;

use App\Enums\Agents\EvalRunTrigger;
use App\Enums\Queue;
use App\Exceptions\PlanLimitExceededException;
use App\Exceptions\UsageBlockedException;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentEvalSuite;
use App\Services\Agents\EvalRunner;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Re-runs an agent's suites after its behavior changed: queued by
 * `AgentObserver` whenever a new `agent_versions` row is written.
 *
 * Delayed and unique per agent: someone tuning instructions saves many times
 * in a few minutes, and every save is a new version. While one of these jobs
 * is waiting, further changes queue nothing, so the suites run once against
 * wherever the agent ended up rather than once per keystroke-sized edit.
 *
 * Runs are `EvalRunTrigger::AgentChange`, so they simulate every action that
 * would change something. A workspace that can't pay for a run is skipped
 * quietly: this is a background check, not something anyone asked for.
 */
class RunEvalsOnAgentChangeJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const int DELAY_SECONDS = 120;

    public int $tries = 1;

    public function __construct(public readonly string $agentId)
    {
        $this->onQueue(Queue::AiAgent->value);
        $this->delay(self::DELAY_SECONDS);
    }

    public function uniqueId(): string
    {
        return $this->agentId;
    }

    public function handle(EvalRunner $runner): void
    {
        $agent = Agent::find($this->agentId);

        if ($agent === null) {
            return;
        }

        $agent->evalSuites()
            ->where('run_on_change', true)
            ->whereHas('cases')
            ->get()
            ->each(function (AgentEvalSuite $suite) use ($runner): bool {
                try {
                    $runner->start($suite, trigger: EvalRunTrigger::AgentChange);

                    return true;
                } catch (UsageBlockedException|PlanLimitExceededException) {
                    return false;
                }
            });
    }
}
