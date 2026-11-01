<?php

namespace App\Listeners\Workflows;

use App\Actions\Billing\DeductCreditsAction;
use App\Enums\Billing\CreditTransactionType;
use App\Enums\NodeRunStatus;
use App\Enums\Queue;
use App\Events\Runs\RunCancelled;
use App\Events\Runs\RunCompleted;
use App\Events\Runs\RunFailed;
use App\Models\Agents\AgentEvalRun;
use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSession;
use App\Models\Agents\AgentSessionEvaluation;
use App\Models\Agents\ReflectionRun;
use App\Models\Runs\Run;
use App\Models\Workflows\Workflow;
use App\Services\Billing\CreditMeter;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Charges credits for a finished `Run`: one `CreditTransaction` per
 * completed/failed `NodeRun` for a Workflow run (a node routed through an
 * `error` edge still consumed real work), one per turn (the assistant reply)
 * for an Agent session run, or one per graded case for an eval run. See
 * docs/WORKFLOWS_AGENTS_BUILD_PLAN.md Stage 8.
 *
 * A run that fails or is cancelled is billed the same way, for the work it
 * got through: an Ask AI node that answered before a later node failed
 * spent its tokens all the same, as did the cases an eval graded before it
 * timed out. Nothing that never ran is charged: a failed agent turn has no
 * reply to bill, and a node that was still pending or in flight when the
 * run stopped is settled as cancelled, not completed/failed.
 *
 * Queued, so a charge that fails can't fail the engine job that fired
 * `RunCompleted` (`GraphAdvancer`) and send the whole run back through the
 * queue. Charges are idempotent per node run, so the retries this listener
 * does get can't double-bill.
 *
 * Bills with overdraft allowed: these node runs have already executed and
 * cost real money, so the ledger records them even when they overrun the
 * balance. `CreditGate` is what stops the *next* run.
 */
class RecordRunCreditUsage implements ShouldQueue
{
    public string $queue = Queue::Billing->value;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300];

    public function __construct(
        private readonly DeductCreditsAction $deductCredits,
        private readonly CreditMeter $meter,
    ) {}

    public function handle(RunCompleted $event): void
    {
        $this->chargeFor($event->run);
    }

    public function handleRunFailed(RunFailed $event): void
    {
        $this->chargeFor($event->run);
    }

    public function handleRunCancelled(RunCancelled $event): void
    {
        $this->chargeFor($event->run);
    }

    private function chargeFor(Run $run): void
    {
        $run->loadMissing('runnable');

        if ($run->runnable instanceof Workflow) {
            $this->chargeForWorkflowRun($run);

            return;
        }

        if ($run->runnable instanceof AgentSession) {
            $this->chargeForAgentTurn($run);

            return;
        }

        if ($run->runnable instanceof AgentEvalRun) {
            $this->chargeForEvalRun($run->runnable);

            return;
        }

        if ($run->runnable instanceof AgentSessionEvaluation) {
            $this->chargeForSessionEvaluation($run->runnable);

            return;
        }

        if ($run->runnable instanceof ReflectionRun) {
            $this->chargeForReflectionRun($run->runnable);
        }
    }

    private function chargeForReflectionRun(ReflectionRun $reflectionRun): void
    {
        if ($reflectionRun->usage === null) {
            return;
        }

        $this->deductCredits->execute(
            $reflectionRun->workspace,
            CreditTransactionType::Reflection,
            $reflectionRun->id,
            $this->meter->costForReflectionRun($reflectionRun),
            'Agent reflection',
            allowOverdraft: true,
        );
    }

    /**
     * One charge per evaluation. An evaluation that failed before the judge
     * answered carries no usage and is skipped: nothing was spent on it.
     */
    private function chargeForSessionEvaluation(AgentSessionEvaluation $evaluation): void
    {
        if ($evaluation->usage === null) {
            return;
        }

        $this->deductCredits->execute(
            $evaluation->workspace,
            CreditTransactionType::SessionEvaluation,
            $evaluation->id,
            $this->meter->costForSessionEvaluation($evaluation),
            'Session evaluation',
            allowOverdraft: true,
        );
    }

    private function chargeForWorkflowRun(Run $run): void
    {
        $nodeRuns = $run->nodeRuns()
            ->whereIn('status', [NodeRunStatus::Completed, NodeRunStatus::Failed])
            ->get();

        foreach ($nodeRuns as $nodeRun) {
            $this->deductCredits->execute(
                $run->workspace,
                CreditTransactionType::NodeRun,
                $nodeRun->id,
                $this->meter->costForNodeRun($nodeRun),
                "Node '{$nodeRun->key}' ({$nodeRun->type})",
                allowOverdraft: true,
            );
        }
    }

    /**
     * One charge per graded case. Cases that errored before the model
     * answered carry no usage and are skipped: nothing was spent on them.
     */
    private function chargeForEvalRun(AgentEvalRun $evalRun): void
    {
        $results = $evalRun->results()->whereNotNull('usage')->get();

        foreach ($results as $result) {
            $this->deductCredits->execute(
                $evalRun->workspace,
                CreditTransactionType::EvalCase,
                $result->id,
                $this->meter->costForEvalCase($result),
                'Eval case',
                allowOverdraft: true,
            );
        }
    }

    private function chargeForAgentTurn(Run $run): void
    {
        $messageId = $run->output['message_id'] ?? null;
        $message = $messageId !== null ? AgentMessage::find($messageId) : null;

        if ($message === null) {
            return;
        }

        $this->deductCredits->execute(
            $run->workspace,
            CreditTransactionType::AgentStep,
            $message->id,
            $this->meter->costForAgentMessage($message),
            'Agent turn',
            allowOverdraft: true,
        );
    }
}
