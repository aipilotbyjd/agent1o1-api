<?php

namespace App\Services\Workflows\Engine;

use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Events\Runs\RunFailed;
use App\Exceptions\InvalidNodeInputException;
use App\Exceptions\RunBudgetExceededException;
use App\Jobs\Workflows\DispatchNextNodesJob;
use App\Jobs\Workflows\ExecuteNodeJob;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\Workflows\WorkflowEdge;
use App\Services\Secrets\PlatformSecrets;
use App\Services\Workflows\StepOptions;
use Throwable;

/**
 * Retry-vs-route-vs-fail policy for a failed `NodeRun`: ported design from
 * docs/WORKFLOWS_PLAN.md's `StepFailureHandler` section: exponential backoff
 * with jitter, redact credentials from the persisted error, and only route
 * down an `error` edge or fail the whole run once retries are exhausted.
 */
class StepFailureHandler
{
    public function __construct(
        private readonly PlatformSecrets $platformSecrets,
        private readonly RunCanceller $canceller,
    ) {}

    /**
     * @param  array{key: string, type: string, config: array<string, mixed>}  $nodeDefinition
     * @param  array{nodes: array<int, array{key: string, type: string, config: array<string, mixed>}>, edges: array<int, array{from: string, to: string, condition: string|null}>}  $graph
     * @param  array<int, string>  $secretValues  plaintext of the secrets this step used, from `ResolvedSecrets::sensitiveValues()`
     */
    public function handle(Run $run, NodeRun $nodeRun, array $nodeDefinition, array $graph, Throwable $e, array $secretValues = []): void
    {
        $options = StepOptions::fromNodeConfig($nodeDefinition['config'] ?? []);
        $message = $this->redact($e->getMessage(), $secretValues);

        // Only a node still `running` can fail or be retried: one cancelled
        // meanwhile stays cancelled, and neither retries nor a failed run
        // may resurrect it.
        if ($nodeRun->attempt < $options->maxAttempts && ! $e instanceof InvalidNodeInputException && ! $e instanceof RunBudgetExceededException) {
            $this->retry($nodeRun, $options, $message);

            return;
        }

        if (! $nodeRun->transitionFrom([NodeRunStatus::Running], [
            'status' => NodeRunStatus::Failed,
            'error' => $message,
            'finished_at' => now(),
        ])) {
            return;
        }

        $this->routeFailureOrFailRun($run, $nodeRun, $graph, $options->continueOnError);
    }

    /**
     * Given an already-`Failed` `NodeRun`, either route down its `error`
     * edge (or, with `continue_on_error`, its unconditional edges), or fail
     * the whole run. Shared by the retries-exhausted path above and by
     * approval-rejection (`WorkflowRunner::resolveApproval()`) / wait-timeout
     * (`WorkflowRunner::expireWait()`), which fail a `NodeRun` without going
     * through a retry loop first.
     *
     * @param  array{nodes: array<int, array{key: string, type: string, config: array<string, mixed>}>, edges: array<int, array{from: string, to: string, condition: string|null}>}  $graph
     */
    public function routeFailureOrFailRun(Run $run, NodeRun $nodeRun, array $graph, bool $continueOnError = false): void
    {
        $hasErrorEdge = collect($graph['edges'])->contains(
            fn (array $edge) => $edge['from'] === $nodeRun->key && $edge['condition'] === WorkflowEdge::ERROR_CONDITION,
        );

        if ($hasErrorEdge || $continueOnError) {
            DispatchNextNodesJob::afterSettling($run->id, $nodeRun->id, $continueOnError && ! $hasErrorEdge);

            return;
        }

        $this->failRun($run, $nodeRun->error ?? 'Node failed.');
    }

    private function retry(NodeRun $nodeRun, StepOptions $options, string $message): void
    {
        $delay = $this->backoffSeconds($nodeRun->attempt, $options->retryDelaySeconds);

        if (! $nodeRun->transitionFrom([NodeRunStatus::Running], [
            'status' => NodeRunStatus::Pending,
            'attempt' => $nodeRun->attempt + 1,
            'error' => $message,
        ])) {
            return;
        }

        ExecuteNodeJob::dispatch($nodeRun->id)->delay(now()->addSeconds($delay));
    }

    /**
     * `min(base * 2^(attempt-1), 3600)` with ±25% jitter.
     */
    private function backoffSeconds(int $attempt, int $base): int
    {
        $base = max($base, 1);
        $exponential = min($base * (2 ** ($attempt - 1)), 3600);
        $jitter = $exponential * random_int(-25, 25) / 100;

        return max(0, (int) round($exponential + $jitter));
    }

    /**
     * A run that fails abandons its in-flight work exactly as a cancelled
     * one does, and for the same reasons, hence the shared `RunCanceller`.
     * Settling only `pending`/`running` nodes here used to leave the two
     * parked states behind: an approval on a failed run stayed actionable,
     * and a `Wait` node's public callback URL kept resolving. Child runs
     * a `subflow`/`loop` node started were left running too, billing for
     * output whose parent node has already settled.
     */
    private function failRun(Run $run, string $message): void
    {
        // Before the run's own status flips, so a `NodeRunObserver` broadcast
        // and the `RunFailed` listeners see a consistent picture. The node
        // that caused the failure is already terminal, so it keeps its own
        // `failed` status and error message.
        $run->refresh();

        // A run cancelled (or already failed) while this node was failing
        // keeps that outcome.
        if ($run->status->isTerminal()) {
            return;
        }

        $this->canceller->settleInFlightNodeRuns($run);

        $terminal = collect(RunStatus::cases())->filter(fn (RunStatus $status): bool => $status->isTerminal())->map->value->all();

        $failed = Run::query()
            ->whereKey($run->id)
            ->whereNotIn('status', $terminal)
            ->update(['status' => RunStatus::Failed->value, 'error' => $message, 'finished_at' => now()]);

        if ($failed === 0) {
            return;
        }

        $run->refresh();

        // After the parent is terminal: a child settling mid-cancellation
        // resumes its parent node (`WorkflowRunner::resolveSubWorkflow()`),
        // which must find a run that has already stopped rather than one
        // still advertising itself as in flight.
        $this->canceller->cancelChildRuns($run);

        event(new RunFailed($run));
    }

    /**
     * The plaintext of the secrets this step actually resolved (the only
     * thing that catches a secret that isn't key-shaped), the platform's own
     * credentials, and anything key-shaped: see `PlatformSecrets`.
     *
     * @param  array<int, string>  $secretValues
     */
    private function redact(string $message, array $secretValues = []): string
    {
        return $this->platformSecrets->redact($message, $secretValues);
    }
}
