<?php

namespace App\Services\Workflows;

use App\Contracts\NodeContract;
use App\Enums\NodeRunStatus;
use App\Enums\RunStatus;
use App\Enums\Workflows\FlowControlNodeType;
use App\Events\Runs\RunCompleted;
use App\Events\Runs\RunFailed;
use App\Exceptions\WorkflowValidationException;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowNode;
use App\Services\Billing\CreditGate;
use App\Services\Secrets\SecretRedactor;
use App\Services\Secrets\SecretResolver;
use Throwable;

/**
 * Runs a single draft node on demand: the "test this step" button in the
 * editor, without publishing a version or executing the rest of the graph.
 *
 * This executes for real: the node's `NodeContract::execute()` is called, so
 * a Slack node posts an actual message. It is deliberately *not* a dry run
 * (`DryRunner` is, and calls nothing external), and it is deliberately
 * recorded: a real `Run` (`trigger_type = node_test`) plus its one
 * `NodeRun`, so the work shows up in run history and is metered by
 * `RecordRunCreditUsage` exactly like any other node execution. Hiding real
 * spend from the ledger would be the only alternative, and it isn't one.
 *
 * Mirrors `WorkflowRunner::executeNodeContract()`'s handling of templating,
 * secret resolution and output redaction; what it leaves out is everything
 * graph-shaped (traversal, retries, error edges), which is meaningless for a
 * single step.
 */
class NodeTester
{
    /**
     * `runs.trigger_type` for a single-node test: distinct from `manual` so
     * these never masquerade as a real execution in run history, and so
     * pinned data (which only short-circuits `manual` runs) can't stop a test
     * from actually calling out.
     */
    public const string TRIGGER_TYPE = 'node_test';

    public function __construct(
        private readonly NodeRegistry $registry,
        private readonly ConfigSchemaValidator $configValidator,
        private readonly TemplateResolver $templateResolver,
        private readonly ResolvedConfigCaster $resolvedConfigCaster,
        private readonly SecretResolver $secretResolver,
        private readonly SecretRedactor $secretRedactor,
        private readonly CreditGate $creditGate,
    ) {}

    /**
     * @param  array<string, mixed>  $input  stands in for the run input the node would have seen
     * @param  array<string, mixed>  $upstreamOutputs  keyed by node key: stands in for `context.nodes`
     * @param  array<string, mixed>|null  $configOverride  unsaved editor config; falls back to the stored one
     *
     * @throws WorkflowValidationException when the node type can't be tested standalone, or its config is invalid
     */
    public function test(
        Workflow $workflow,
        WorkflowNode $node,
        array $input = [],
        array $upstreamOutputs = [],
        ?User $triggeredBy = null,
        ?array $configOverride = null,
    ): NodeRun {
        $config = EditorMetadata::strip($configOverride ?? $node->config ?? []);

        $contract = $this->executableContract($node);
        $this->assertConfigValid($contract, $node, $config);

        $this->creditGate->assertCanStartRun($workflow->workspace);

        $run = $this->openRun($workflow, $node, $input, $triggeredBy);
        $nodeRun = $run->nodeRuns()->create([
            'key' => $node->key,
            'type' => $node->type,
            'input' => $input,
        ]);
        $nodeRun->forceFill(['status' => NodeRunStatus::Running, 'started_at' => now()])->save();

        $context = ['input' => $input, 'nodes' => [...WorkflowIo::inputOutputs($workflow->draftGraph(), $input), ...$upstreamOutputs]];
        $secrets = $this->secretResolver->forConfig($workflow->workspace_id, $config);
        $resolvedConfig = $this->templateResolver->resolve($config, [...$context, ...$secrets->context()]);

        try {
            $secrets->assertComplete();
            $output = $this->secretRedactor->redact(
                $contract->execute($run, $this->resolvedConfigCaster->cast($contract->configSchema(), $config, $resolvedConfig), $context),
                $secrets->sensitiveValues(),
            );

            $nodeRun->forceFill([
                'status' => NodeRunStatus::Completed,
                'output' => $output,
                'usage' => $output['usage'] ?? null,
                'finished_at' => now(),
            ])->save();

            $run->forceFill([
                'status' => RunStatus::Completed,
                'output' => [$node->key => $output],
                'finished_at' => now(),
            ])->save();

            event(new RunCompleted($run));
        } catch (Throwable $e) {
            // A node that throws is a *successful* test (the editor wants to
            // see the error, not a 500) so the failure is recorded on the
            // node run and returned rather than rethrown.
            $message = $this->secretRedactor->redactString($e->getMessage(), $secrets->sensitiveValues());

            $nodeRun->forceFill([
                'status' => NodeRunStatus::Failed,
                'error' => $message,
                'finished_at' => now(),
            ])->save();

            $run->forceFill([
                'status' => RunStatus::Failed,
                'error' => $message,
                'finished_at' => now(),
            ])->save();

            event(new RunFailed($run));
        }

        return $nodeRun->fresh();
    }

    /**
     * @throws WorkflowValidationException
     */
    private function executableContract(WorkflowNode $node): NodeContract
    {
        if (FlowControlNodeType::tryFrom($node->type) !== null) {
            throw new WorkflowValidationException([
                "Node '{$node->key}' is a flow-control node ({$node->type}): it only has meaning inside a full run and can't be tested on its own.",
            ]);
        }

        if (! $this->registry->has($node->type)) {
            throw new WorkflowValidationException([
                "Node '{$node->key}' has an unknown type '{$node->type}'.",
            ]);
        }

        return $this->registry->resolve($node->type);
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws WorkflowValidationException
     */
    private function assertConfigValid(NodeContract $contract, WorkflowNode $node, array $config): void
    {
        $errors = array_map(
            fn (array $issue): ValidationIssue => ValidationIssue::forNodeConfig($node->key, $issue),
            $this->configValidator->issues($contract->configSchema(), $config),
        );

        if ($errors !== []) {
            throw new WorkflowValidationException($errors);
        }
    }

    /**
     * The test's `Run` is intentionally left unpinned (`workflow_version_id`
     * null): a draft node is being tested, and there may be no published
     * version at all yet.
     *
     * @param  array<string, mixed>  $input
     */
    private function openRun(Workflow $workflow, WorkflowNode $node, array $input, ?User $triggeredBy): Run
    {
        $run = $workflow->runs()->create([
            'workspace_id' => $workflow->workspace_id,
            'runnable_type' => $workflow->getMorphClass(),
            'runnable_id' => $workflow->id,
            'workflow_id' => $workflow->id,
            'trigger_type' => self::TRIGGER_TYPE,
            'input' => $input,
            'triggered_by' => $triggeredBy?->id,
        ]);

        $run->forceFill(['status' => RunStatus::Running, 'started_at' => now()])->save();

        return $run;
    }
}
