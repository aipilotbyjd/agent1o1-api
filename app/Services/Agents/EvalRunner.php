<?php

namespace App\Services\Agents;

use App\Enums\Agents\EvalRunStatus;
use App\Enums\Agents\EvalRunTrigger;
use App\Enums\RunStatus;
use App\Events\Runs\RunCompleted;
use App\Events\Runs\RunFailed;
use App\Jobs\Agents\RunAgentEvalJob;
use App\Models\Agents\AgentEvalCase;
use App\Models\Agents\AgentEvalCaseResult;
use App\Models\Agents\AgentEvalRun;
use App\Models\Agents\AgentEvalSuite;
use App\Models\Runs\Run;
use App\Models\User;
use App\Notifications\Agents\EvalRegressionNotification;
use App\Services\Billing\CreditGate;
use App\Services\Notifications\NotificationDispatcher;
use Throwable;

/**
 * Runs a suite against its agent and records how every case graded.
 *
 * Each case is a fresh, stateless turn (`AgentRunner::ask()`), never a shared
 * conversation: cases must not be able to influence one another, or a suite's
 * result would depend on the order its cases happen to be in.
 *
 * **Cases execute for real, tools included.** That is the point: an eval of
 * an agent whose tools were stubbed would grade a different agent than the
 * one in production, but it does mean a suite aimed at a tool-using agent
 * can cause real side effects, exactly as chatting with that agent would.
 * Suites for such agents should be written against read-only prompts.
 *
 * The whole grading pass is recorded as one `Run` (`runnable_type =
 * AgentEvalRun`), which is what puts eval spend on the same ledger as
 * everything else: see `RecordRunCreditUsage`. A case's recorded usage
 * includes its rubric judge's tokens, so judging is billed too.
 *
 * `start()` is the HTTP entry point: a suite makes one model call per case
 * (plus a judge call per rubric), too slow to run inside a request, so it
 * queues `RunAgentEvalJob`, which calls `execute()`. `run()` does both in
 * one go for callers that want the result inline.
 *
 * A completed run is compared with the suite's previous completed run; one
 * that passes a smaller share of its cases is marked `regressed`, and when
 * nobody started it by hand (`EvalRunTrigger::AgentChange`) the workspace's
 * owners and admins are told.
 */
class EvalRunner
{
    public function __construct(
        private readonly AgentRunner $agentRunner,
        private readonly AgentVersioner $versioner,
        private readonly AssertionGrader $grader,
        private readonly CreditGate $creditGate,
        private readonly NotificationDispatcher $notifications,
    ) {}

    /**
     * Records a pending eval run and queues its execution.
     */
    public function start(AgentEvalSuite $suite, ?User $triggeredBy = null, EvalRunTrigger $trigger = EvalRunTrigger::Manual): AgentEvalRun
    {
        $evalRun = $this->createEvalRun($suite, $triggeredBy, $trigger);

        RunAgentEvalJob::dispatch($evalRun->id);

        return $evalRun->fresh();
    }

    public function run(AgentEvalSuite $suite, ?User $triggeredBy = null, EvalRunTrigger $trigger = EvalRunTrigger::Manual): AgentEvalRun
    {
        return $this->execute($this->createEvalRun($suite, $triggeredBy, $trigger));
    }

    /**
     * Grades every case of a pending eval run. A run that has already left
     * `pending` is returned untouched, so a redelivered job can't grade (and
     * bill) the same suite twice.
     */
    public function execute(AgentEvalRun $evalRun): AgentEvalRun
    {
        if ($evalRun->status !== EvalRunStatus::Pending) {
            return $evalRun;
        }

        $suite = $evalRun->suite;

        $evalRun->forceFill(['status' => EvalRunStatus::Running, 'started_at' => now()])->save();

        $run = null;

        try {
            $run = $this->openRun($evalRun, $suite, $evalRun->triggeredBy);

            [$passed, $failed] = $this->gradeCases($suite, $evalRun, $run);

            $evalRun->forceFill([
                'status' => EvalRunStatus::Completed,
                'passed' => $passed,
                'failed' => $failed,
                'regressed' => $this->regressed($evalRun, $passed, $failed),
                'finished_at' => now(),
            ])->save();

            $run->forceFill([
                'status' => RunStatus::Completed,
                'output' => ['passed' => $passed, 'failed' => $failed],
                'finished_at' => now(),
            ])->save();

            event(new RunCompleted($run));

            if ($evalRun->regressed && $evalRun->trigger === EvalRunTrigger::AgentChange) {
                $this->notifications->dispatch(
                    $this->notifications->ownersAndAdmins($suite->workspace),
                    new EvalRegressionNotification($evalRun),
                );
            }
        } catch (Throwable $e) {
            // Only something outside an individual case can land here: a
            // single case's failure is caught per case and recorded as a
            // failing result, since one broken case shouldn't discard the
            // evidence from all the others.
            $this->fail($evalRun, $e);
        }

        return $evalRun->fresh();
    }

    /**
     * Marks an eval run and its `Run` failed, unless already finished. Also
     * what `RunAgentEvalJob::failed()` calls when the worker itself dies
     * (e.g. a timeout), so the run can't be left `running`.
     */
    public function fail(AgentEvalRun $evalRun, Throwable $e): void
    {
        $evalRun->refresh();

        if (in_array($evalRun->status, [EvalRunStatus::Completed, EvalRunStatus::Failed], true)) {
            return;
        }

        $evalRun->forceFill([
            'status' => EvalRunStatus::Failed,
            'error' => $e->getMessage(),
            'finished_at' => now(),
        ])->save();

        $run = $evalRun->runs()->latest('id')->first();

        if ($run !== null && ! $run->status->isTerminal()) {
            $run->forceFill([
                'status' => RunStatus::Failed,
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ])->save();

            event(new RunFailed($run));
        }
    }

    /**
     * The credit gate runs here, before anything is queued: a workspace out
     * of credits is refused at request time, not discovered by the job.
     */
    private function createEvalRun(AgentEvalSuite $suite, ?User $triggeredBy, EvalRunTrigger $trigger): AgentEvalRun
    {
        $this->creditGate->assertCanStartRun($suite->workspace);

        return $suite->runs()->create([
            'workspace_id' => $suite->workspace_id,
            // Records which behavior was graded: see the migration.
            'agent_version_id' => $this->versioner->currentVersion($suite->agent)->id,
            'trigger' => $trigger,
            'triggered_by' => $triggeredBy?->id,
        ]);
    }

    /**
     * Whether this run passed a smaller share of its cases than the suite's
     * previous completed run. Shares rather than counts, since cases may have
     * been added or removed in between.
     */
    private function regressed(AgentEvalRun $evalRun, int $passed, int $failed): bool
    {
        $previous = AgentEvalRun::query()
            ->where('agent_eval_suite_id', $evalRun->agent_eval_suite_id)
            ->where('status', EvalRunStatus::Completed)
            ->whereKeyNot($evalRun->id)
            ->latest('finished_at')
            ->first();

        if ($previous === null || $previous->passed + $previous->failed === 0 || $passed + $failed === 0) {
            return false;
        }

        return $passed / ($passed + $failed) < $previous->passed / ($previous->passed + $previous->failed);
    }

    /**
     * @return array{0: int, 1: int} passed and failed case counts
     */
    private function gradeCases(AgentEvalSuite $suite, AgentEvalRun $evalRun, Run $run): array
    {
        $passed = 0;
        $failed = 0;

        foreach ($suite->cases as $case) {
            $result = $this->gradeCase($case, $evalRun, $run);

            $result->passed ? $passed++ : $failed++;
        }

        return [$passed, $failed];
    }

    private function gradeCase(AgentEvalCase $case, AgentEvalRun $evalRun, Run $run): AgentEvalCaseResult
    {
        $result = $evalRun->results()->create([
            'agent_eval_case_id' => $case->id,
        ]);

        try {
            $answer = $this->agentRunner->ask($evalRun->suite->agent, $run, $case->input, $evalRun->trigger->simulatesActions());
        } catch (Throwable $e) {
            $result->forceFill(['passed' => false, 'error' => $e->getMessage()])->save();

            return $result;
        }

        $usage = $answer['usage'];
        $graded = [];

        foreach ($case->assertions ?? [] as $assertion) {
            $grade = $this->grader->grade($assertion, $answer['text'], $evalRun->suite->agent, $answer['tool_calls']);

            // The judge's tokens are real spend on this case: fold them into
            // the case's usage rather than storing them per assertion.
            if (isset($grade['usage'])) {
                $usage = $this->addUsage($usage, $grade['usage']);
                unset($grade['usage']);
            }

            $graded[] = $grade;
        }

        $result->forceFill([
            'output' => $answer['text'],
            'usage' => $usage,
            'assertions' => $graded,
            // A case with no assertions can't fail, but it also proves
            // nothing: the API refuses to create one, so this only guards
            // against rows written before that rule existed.
            'passed' => ! in_array(false, array_column($graded, 'passed'), true),
        ])->save();

        return $result;
    }

    /**
     * @param  array<string, mixed>  $total
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function addUsage(array $total, array $extra): array
    {
        foreach ($extra as $key => $value) {
            $total[$key] = (int) ($total[$key] ?? 0) + (int) $value;
        }

        return $total;
    }

    private function openRun(AgentEvalRun $evalRun, AgentEvalSuite $suite, ?User $triggeredBy): Run
    {
        $run = $evalRun->runs()->create([
            'workspace_id' => $suite->workspace_id,
            'trigger_type' => 'eval',
            'input' => ['suite_id' => $suite->id],
            'triggered_by' => $triggeredBy?->id,
        ]);

        $run->forceFill(['status' => RunStatus::Running, 'started_at' => now()])->save();

        return $run;
    }
}
