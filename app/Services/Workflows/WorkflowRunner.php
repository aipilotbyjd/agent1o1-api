<?php

namespace App\Services\Workflows;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\AgentActionStatus;
use App\Enums\NodeRunStatus;
use App\Enums\Triggers\TriggerType;
use App\Enums\Workflows\FlowControlNodeType;
use App\Exceptions\AgentTurnPausedException;
use App\Jobs\Workflows\DispatchNextNodesJob;
use App\Models\Agents\AgentAction;
use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSession;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\WorkflowApproval;
use App\Nodes\FlowLogic\DelayNode;
use App\Notifications\Workspace\RunApprovalRequestedNotification;
use App\Services\Agents\AgentRunner;
use App\Services\Billing\CostGuard;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Secrets\ResolvedSecrets;
use App\Services\Secrets\SecretRedactor;
use App\Services\Secrets\SecretResolver;
use App\Services\Workflows\Engine\JoinResults;
use App\Services\Workflows\Engine\LoopCoordinator;
use App\Services\Workflows\Engine\RunCanceller;
use App\Services\Workflows\Engine\StepFailureHandler;
use App\Services\Workflows\Engine\SubWorkflowCoordinator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * The engine's single entry point: callers (jobs, controllers, listeners)
 * depend only on this, not on `GraphAdvancer`/`StepFailureHandler`/the
 * coordinators directly. Ported design from docs/WORKFLOWS_PLAN.md's
 * `WorkflowRunner` section. Graph traversal after a node settles is handed
 * off to `DispatchNextNodesJob` (its own `Queue::WorkflowDispatch` queue)
 * rather than called in-process, so `GraphAdvancer` work never runs on the
 * same queue/supervisor as node execution: see docs/STRUCTURE.md's "Queues
 * & Horizon" table.
 */
class WorkflowRunner
{
    public function __construct(
        private readonly NodeRegistry $registry,
        private readonly StepFailureHandler $failureHandler,
        private readonly SubWorkflowCoordinator $subWorkflowCoordinator,
        private readonly LoopCoordinator $loopCoordinator,
        private readonly TemplateResolver $templateResolver,
        private readonly ResolvedConfigCaster $resolvedConfigCaster,
        private readonly NotificationDispatcher $notifications,
        private readonly SecretResolver $secretResolver,
        private readonly SecretRedactor $secretRedactor,
        private readonly RunCanceller $canceller,
        private readonly CostGuard $costGuard,
    ) {}

    public function executeStep(NodeRun $nodeRun): void
    {
        // A retried or racing dispatch landing on an already-settled node is
        // a no-op, not a bug: the unique (run_id, key) constraint already
        // prevented a duplicate row; this guards the same node being
        // executed twice by two racing workers.
        if ($nodeRun->status !== NodeRunStatus::Pending) {
            return;
        }

        $run = $nodeRun->run;

        // A job queued before the run was cancelled can still be picked up
        // afterwards: `cancel()` settles every node row it can see, but not
        // one a racing `DispatchNextNodesJob` created after it ran.
        if ($run->status->isTerminal()) {
            $nodeRun->forceFill(['status' => NodeRunStatus::Cancelled, 'finished_at' => now()])->save();

            return;
        }
        $graph = $run->workflowVersion->graph;
        $context = $this->buildContext($run, $nodeRun, $graph);

        // Workspace secrets are merged into the *templating* context only,
        // never into the `$context` handed to `NodeContract::execute()`, so a
        // node only ever sees the values its own config asked for by name.
        $secrets = $this->secretsForNode($run, $graph, $nodeRun->key);
        $nodeDefinition = $this->resolvedNodeDefinition($graph, $nodeRun->key, [...$context, ...$secrets->context()]);

        // The status check above is on a model another worker may have loaded
        // at the same moment; the claim is the actual exclusivity check: two
        // workers racing here get exactly one winner, and a node whose run
        // was cancelled in between is left cancelled.
        if (! $nodeRun->transitionFrom([NodeRunStatus::Pending], ['status' => NodeRunStatus::Running, 'started_at' => now()])) {
            return;
        }

        match ($nodeRun->type) {
            FlowControlNodeType::HumanApproval->value => $this->pauseForApproval($run, $nodeRun, $nodeDefinition),
            FlowControlNodeType::Wait->value => $this->pauseForWait($nodeRun, $nodeDefinition),
            // The child run stores its input and every output built from it,
            // so a secret is never passed down: the sub-workflow references
            // `{{ secrets.X }}` itself, as the same workspace's store is its.
            FlowControlNodeType::SubWorkflow->value => $this->subWorkflowCoordinator->start(
                $run,
                $nodeRun,
                $this->secretRedactor->redact($nodeDefinition, $secrets->sensitiveValues()),
            ),
            FlowControlNodeType::Loop->value => $this->loopCoordinator->start($run, $nodeRun, $nodeDefinition, $context),
            // GraphAdvancer::joinReadiness() only ever dispatches a
            // join_paths node once it's genuinely ready, by the time
            // WorkflowRunner sees it, there's nothing left to do but settle
            // it immediately and let traversal continue.
            FlowControlNodeType::JoinPaths->value => $this->completeJoin($run, $nodeRun, $nodeDefinition, $graph),
            default => $this->executeNodeContract($run, $nodeRun, $nodeDefinition, $graph, $context, $secrets),
        };
    }

    /**
     * Node config is templated (`{{ input.name }}`, `{{ nodes.a.result }}`)
     * against the run's context once here, before any of `executeStep()`'s
     * branches see it: see `TemplateResolver`.
     *
     * @param  array{nodes: array<int, array{key: string, type: string, config: array<string, mixed>, pinned_data?: array<string, mixed>|null}>, edges: array<int, array{from: string, to: string, condition: string|null}>}  $graph
     * @param  array<string, mixed>  $context
     * @return array{key: string, type: string, config: array<string, mixed>, pinned_data?: array<string, mixed>|null}
     */
    private function resolvedNodeDefinition(array $graph, string $key, array $context): array
    {
        $nodeDefinition = collect($graph['nodes'])->firstWhere('key', $key) ?? ['key' => $key, 'type' => null, 'config' => []];

        return [
            ...$nodeDefinition,
            'config' => $this->templateResolver->resolve(EditorMetadata::strip($nodeDefinition['config'] ?? []), $context),
        ];
    }

    /**
     * The workspace secrets this node's raw (un-templated) config references,
     * loaded per step so nothing else in the workspace is ever in memory:
     * see `SecretResolver`.
     *
     * @param  array{nodes: array<int, array{key: string, type: string, config: array<string, mixed>}>, edges: array<int, array{from: string, to: string, condition: string|null}>}  $graph
     */
    private function secretsForNode(Run $run, array $graph, string $key): ResolvedSecrets
    {
        $config = collect($graph['nodes'])->firstWhere('key', $key)['config'] ?? [];

        return $this->secretResolver->forConfig($run->workspace_id, EditorMetadata::strip($config));
    }

    /**
     * @param  array{key: string, type: string, config: array<string, mixed>}  $nodeDefinition
     * @param  array{nodes: array<int, array{key: string, type: string, config: array<string, mixed>}>, edges: array<int, array{from: string, to: string, condition: string|null}>}  $graph
     * @param  array<string, mixed>  $context
     */
    private function executeNodeContract(Run $run, NodeRun $nodeRun, array $nodeDefinition, array $graph, array $context, ResolvedSecrets $secrets = new ResolvedSecrets): void
    {
        // Pin data (see `WorkflowNode::pinned_data`) only short-circuits
        // execution for manual test runs: production trigger types
        // (webhook/schedule/polling) always execute for real, so a pin left
        // on from testing can never silently affect a live automation.
        $pinnedData = $nodeDefinition['pinned_data'] ?? null;

        if ($pinnedData !== null && $run->trigger_type === TriggerType::Manual->value) {
            if (! $nodeRun->transitionFrom([NodeRunStatus::Running], [
                'status' => NodeRunStatus::Completed,
                'output' => $pinnedData,
                'finished_at' => now(),
            ])) {
                return;
            }

            DispatchNextNodesJob::afterSettling($run->id, $nodeRun->id);

            return;
        }

        try {
            $secrets->assertComplete();
            $this->costGuard->assertRunWithinBudget($run);
            $node = $this->registry->resolve($nodeRun->type);
            $config = $this->resolvedConfigCaster->cast(
                $node->configSchema(),
                EditorMetadata::strip(collect($graph['nodes'])->firstWhere('key', $nodeRun->key)['config'] ?? []),
                $nodeDefinition['config'] ?? [],
            );

            // A node that echoes its own config back (an HTTP node returning
            // the request it made, an API replying with the token it was
            // called with) would otherwise write the plaintext secret into
            // `node_runs`, readable by every run-viewer in the workspace.
            // A reader node that started this run as its flow trigger outputs
            // the item it fired for, rather than reading the list again.
            $output = $this->secretRedactor->redact(
                $node instanceof SupportsTriggerMode && $run->entry_node_key === $nodeRun->key
                    ? (array) ($context['input'] ?? [])
                    : $node->execute($run, $config, $context),
                $secrets->sensitiveValues(),
            );

            // Conditional on still being `running`: a cancellation that landed
            // while the node was executing stays cancelled.
            if (! $nodeRun->transitionFrom([NodeRunStatus::Running], [
                'status' => NodeRunStatus::Completed,
                'output' => $output,
                // Nodes that call an LLM (AskAiNode) report a `usage` key in
                // their output: lifted onto the dedicated column too, so
                // `CreditMeter`/reporting can read it without knowing which
                // node types happen to embed it in their output shape.
                'usage' => $output['usage'] ?? null,
                'finished_at' => now(),
            ])) {
                return;
            }

            // DelayNode reports how long to wait rather than blocking a
            // worker inside execute(): the engine applies the real delay
            // to the next dispatch instead.
            $delaySeconds = $nodeRun->type === (new DelayNode)->type() ? (int) ($output['seconds'] ?? 0) : 0;

            $pending = DispatchNextNodesJob::afterSettling($run->id, $nodeRun->id);

            if ($delaySeconds > 0) {
                $pending->delay(now()->addSeconds($delaySeconds));
            }
        } catch (AgentTurnPausedException $e) {
            $this->pauseForAgentApproval($nodeRun, $e);
        } catch (Throwable $e) {
            Log::warning('Workflow node execution failed.', [
                'run_id' => $run->id,
                'node_key' => $nodeRun->key,
                'node_type' => $nodeRun->type,
                'attempt' => $nodeRun->attempt,
                'exception' => $this->secretRedactor->redactString($e->getMessage(), $secrets->sensitiveValues()),
            ]);

            $this->failureHandler->handle($run, $nodeRun, $nodeDefinition, $graph, $e, $secrets->sensitiveValues());
        }
    }

    /**
     * @param  array{key: string, type: string, config: array<string, mixed>}  $nodeDefinition
     * @param  array<string, mixed>  $graph
     */
    private function completeJoin(Run $run, NodeRun $nodeRun, array $nodeDefinition, array $graph): void
    {
        $output = (new JoinResults)->combine($run, $nodeRun->key, $graph, JoinResults::mode($nodeDefinition['config'] ?? []));

        $nodeRun->forceFill([
            'status' => NodeRunStatus::Completed,
            'started_at' => now(),
            'output' => $output,
            'finished_at' => now(),
        ])->save();

        DispatchNextNodesJob::afterSettling($run->id, $nodeRun->id);
    }

    /**
     * An Agent node whose agent stopped on an action needing approval: the
     * node waits (`awaiting_approval`, like a HumanApproval node) with its
     * conversation noted in `output`, and the waiting actions are tied to
     * it so a decision resumes the node: see `resumeAgentNode()`.
     */
    private function pauseForAgentApproval(NodeRun $nodeRun, AgentTurnPausedException $paused): void
    {
        if (! $nodeRun->transitionFrom([NodeRunStatus::Running], [
            'status' => NodeRunStatus::AwaitingApproval,
            'output' => [
                'awaiting_approval' => true,
                'agent_session_id' => $paused->session->id,
                'message_id' => $paused->agentMessage->id,
            ],
        ])) {
            return;
        }

        AgentAction::query()
            ->where('agent_message_id', $paused->agentMessage->id)
            ->where('status', AgentActionStatus::Pending)
            ->update(['node_run_id' => $nodeRun->id]);
    }

    /**
     * Continues an Agent node once its waiting actions are decided. Claimed
     * with a conditional update, so a second resume of the same node is a
     * no-op. The node then settles like any other: completed and the graph
     * advances, paused again on a new action, or failed through the usual
     * error handling.
     */
    public function resumeAgentNode(NodeRun $nodeRun): void
    {
        $claimed = NodeRun::query()
            ->whereKey($nodeRun->id)
            ->where('status', NodeRunStatus::AwaitingApproval)
            ->update(['status' => NodeRunStatus::Running, 'updated_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        $nodeRun->refresh();
        $run = $nodeRun->run;

        if ($run->status->isTerminal()) {
            $nodeRun->forceFill(['status' => NodeRunStatus::Cancelled, 'finished_at' => now()])->save();

            return;
        }

        $session = AgentSession::query()->find($nodeRun->output['agent_session_id'] ?? null);
        $message = AgentMessage::query()->find($nodeRun->output['message_id'] ?? null);

        try {
            if ($session === null || $message === null) {
                throw new RuntimeException('The paused agent conversation no longer exists.');
            }

            $output = app(AgentRunner::class)->resumeInConversation($run, $session, $message);

            if (! $nodeRun->transitionFrom([NodeRunStatus::Running], [
                'status' => NodeRunStatus::Completed,
                'output' => $output,
                'usage' => $output['usage'] ?? null,
                'finished_at' => now(),
            ])) {
                return;
            }

            DispatchNextNodesJob::afterSettling($run->id, $nodeRun->id);
        } catch (AgentTurnPausedException $e) {
            $this->pauseForAgentApproval($nodeRun, $e);
        } catch (Throwable $e) {
            $graph = $run->workflowVersion->graph;
            $nodeDefinition = collect($graph['nodes'])->firstWhere('key', $nodeRun->key) ?? ['key' => $nodeRun->key, 'type' => $nodeRun->type, 'config' => []];

            $this->failureHandler->handle($run, $nodeRun, $nodeDefinition, $graph, $e);
        }
    }

    /**
     * @param  array{key: string, type: string, config: array<string, mixed>}  $nodeDefinition
     */
    private function pauseForApproval(Run $run, NodeRun $nodeRun, array $nodeDefinition): void
    {
        $config = $nodeDefinition['config'] ?? [];

        // What the person deciding is shown, fixed as it stood when the run
        // paused: the title and instructions with their details filled in, and
        // the questions as they were, so editing the workflow later cannot
        // change a decision already waiting.
        $form = [
            'title' => filled($config['title'] ?? null) ? mb_substr(trim((string) $config['title']), 0, 300) : null,
            'instructions' => filled($config['instructions'] ?? null) ? mb_substr(trim((string) $config['instructions']), 0, 5000) : null,
            'fields' => ApprovalForm::fields($config),
        ];

        $nodeRun->forceFill([
            'status' => NodeRunStatus::AwaitingApproval,
            'started_at' => now(),
            'output' => $form['title'] === null && $form['instructions'] === null && $form['fields'] === [] ? null : ['approval_form' => $form],
        ])->save();

        $approval = $nodeRun->approval()->create([
            'run_id' => $run->id,
            'node_run_id' => $nodeRun->id,
            'requested_at' => now(),
        ]);

        $this->notifications->dispatch(
            $this->notifications->ownersAndAdmins($run->workspace),
            new RunApprovalRequestedNotification($run->workspace, $run, $nodeRun, $approval),
        );
    }

    /**
     * @param  array{key: string, type: string, config: array<string, mixed>}  $nodeDefinition
     */
    private function pauseForWait(NodeRun $nodeRun, array $nodeDefinition): void
    {
        $config = $nodeDefinition['config'] ?? [];
        $timeoutSeconds = $config['timeout_seconds'] ?? null;

        $nodeRun->forceFill([
            'status' => NodeRunStatus::AwaitingCallback,
            'started_at' => now(),
            'callback_token' => Str::random(64),
            'callback_expires_at' => $timeoutSeconds !== null ? now()->addSeconds((int) $timeoutSeconds) : null,
        ])->save();
    }

    /**
     * Idempotent: a second decision on an already-decided approval is a
     * silent no-op rather than an error, so a double-submitted click can't
     * fail loudly.
     */
    /**
     * @param  array<string, mixed>  $inputs  the answers to the step's questions, already checked with `ApprovalForm::check()`
     */
    public function resolveApproval(WorkflowApproval $approval, bool $approved, ?User $decidedBy = null, ?string $message = null, array $inputs = []): void
    {
        if ($approval->isDecided()) {
            return;
        }

        $approval->forceFill([
            'decided_at' => now(),
            'decided_by' => $decidedBy?->id,
            'decision' => $approved ? 'approved' : 'rejected',
            'message' => $message,
        ])->save();

        $nodeRun = $approval->nodeRun;
        $run = $nodeRun->run;

        if ($approved) {
            $hasForm = ($nodeRun->output['approval_form']['fields'] ?? []) !== [];

            $nodeRun->forceFill([
                'status' => NodeRunStatus::Completed,
                'output' => [
                    'approved' => true,
                    ...($hasForm ? ['inputs' => $inputs] : []),
                    ...($message !== null && $message !== '' ? ['message' => $message] : []),
                ],
                'finished_at' => now(),
            ])->save();
            DispatchNextNodesJob::afterSettling($run->id, $nodeRun->id);

            return;
        }

        $nodeRun->forceFill([
            'status' => NodeRunStatus::Failed,
            'error' => 'Approval rejected.'.($message !== null ? " {$message}" : ''),
            'finished_at' => now(),
        ])->save();

        $this->failureHandler->routeFailureOrFailRun($run, $nodeRun, $run->workflowVersion->graph);
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ModelNotFoundException
     */
    public function resolveCallback(string $token, array $payload = []): NodeRun
    {
        $nodeRun = NodeRun::where('callback_token', $token)
            ->where('status', NodeRunStatus::AwaitingCallback)
            ->firstOrFail();

        $run = $nodeRun->run;

        // Single-use: null the token out so a redelivered/replayed callback
        // 404s on the second attempt instead of double-resolving.
        $nodeRun->forceFill([
            'status' => NodeRunStatus::Completed,
            'output' => $payload,
            'finished_at' => now(),
            'callback_token' => null,
        ])->save();

        DispatchNextNodesJob::afterSettling($run->id, $nodeRun->id);

        return $nodeRun;
    }

    public function expireWait(NodeRun $nodeRun): void
    {
        if ($nodeRun->status !== NodeRunStatus::AwaitingCallback) {
            return;
        }

        $run = $nodeRun->run;
        $graph = $run->workflowVersion->graph;
        $context = $this->buildContext($run, $nodeRun, $graph);
        $nodeDefinition = $this->resolvedNodeDefinition($graph, $nodeRun->key, $context);
        $continueOnTimeout = (bool) ($nodeDefinition['config']['continue_on_timeout'] ?? false);

        if ($continueOnTimeout) {
            $nodeRun->forceFill([
                'status' => NodeRunStatus::Completed,
                'output' => ['timed_out' => true],
                'finished_at' => now(),
                'callback_token' => null,
            ])->save();

            DispatchNextNodesJob::afterSettling($run->id, $nodeRun->id);

            return;
        }

        $nodeRun->forceFill([
            'status' => NodeRunStatus::Failed,
            'error' => 'Wait timed out.',
            'finished_at' => now(),
            'callback_token' => null,
        ])->save();

        $this->failureHandler->routeFailureOrFailRun($run, $nodeRun, $graph);
    }

    /**
     * Called once a child `Run` (started by a `subflow` or `loop` node)
     * reaches a terminal state: resumes whichever coordinator owns the
     * parent node. See `Listeners\Workflows\{ResumeParentOnChildRunCompleted,
     * ResumeParentOnChildRunFailed}`.
     */
    public function resolveSubWorkflow(Run $childRun): void
    {
        $parentNodeRun = $childRun->parentNode;

        if ($parentNodeRun === null) {
            return;
        }

        match ($parentNodeRun->type) {
            FlowControlNodeType::SubWorkflow->value => $this->subWorkflowCoordinator->resume($childRun, $parentNodeRun),
            FlowControlNodeType::Loop->value => $this->loopCoordinator->resume($childRun, $parentNodeRun),
            default => null,
        };
    }

    /**
     * Stops a run on request: see `RunCanceller`, which owns the mechanics
     * so the failure paths can reuse them without depending on this class.
     * Kept here as the engine's single public entry point: callers depend on
     * `WorkflowRunner`, not on the coordinators behind it.
     */
    public function cancel(Run $run, ?User $cancelledBy = null): Run
    {
        return $this->canceller->cancel($run, $cancelledBy);
    }

    /**
     * Input steps' outputs are seeded from the run input (see `WorkflowIo`),
     * so a step reading one doesn't depend on it having finished first.
     *
     * @param  array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}  $graph
     * @return array{input: array<string, mixed>, nodes: array<string, mixed>}
     */
    private function buildContext(Run $run, NodeRun $nodeRun, array $graph): array
    {
        $upstreamOutputs = $run->nodeRuns()
            ->where('status', NodeRunStatus::Completed)
            ->where('id', '!=', $nodeRun->id)
            ->pluck('output', 'key')
            ->all();

        return [
            'input' => $run->input ?? [],
            'nodes' => [...WorkflowIo::inputOutputs($graph, $run->input ?? []), ...$upstreamOutputs],
        ];
    }
}
