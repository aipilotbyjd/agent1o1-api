<?php

namespace App\Services\Workflows;

use App\Actions\Workflows\StartWorkflowRunAction;
use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Enums\Connectors\ConnectorCredentialScope;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowNode;
use App\Nodes\InputsOutputs\WorkflowInputNode;

/**
 * A sample run lets someone watch a workflow work before trusting it with
 * their real accounts: the onboarding wizard's "Run it" step.
 *
 * It is a real run (`trigger_type = sample`), recorded and metered like any
 * other, with one difference applied by `WorkflowRunner`: every step that
 * would reach outside the workspace is shown instead of executed. App steps
 * (Gmail, Slack, HubSpot...) and any step that declares an external or
 * destructive effect complete with what they would have sent, so nothing is
 * emailed, posted or changed, even when the app is connected. AI steps run
 * for real, which is the part worth seeing. Child runs (sub-workflows, loop
 * iterations) of a sample run are sample runs too.
 *
 * The run form is filled with the template's example answers
 * (`WorkflowTemplate::sample_input`) when the workflow was made from one.
 */
final class SampleRuns
{
    public const string TRIGGER_TYPE = 'sample';

    /**
     * How far up a run's parents to look for a sample run. Nesting deeper
     * than this is refused by the engine long before it is reached.
     */
    private const int MAX_ANCESTORS = 10;

    /**
     * Config keys that pick an app account: never part of what a step
     * "would have sent".
     *
     * @var array<int, string>
     */
    private const array ACCOUNT_FIELDS = ['credential_id', 'access_token'];

    public function __construct(
        private readonly NodeRegistry $registry,
        private readonly StartWorkflowRunAction $startWorkflowRun,
    ) {}

    /**
     * What the run screen shows before the run starts: the run form with
     * example answers, and the app steps that will be previewed, each with
     * whether the app is connected yet.
     *
     * @return array{
     *     fields: array<int, array{key: string, label: string, type: string, required: bool, description: string|null, sample: string|null}>,
     *     app_steps: array<int, array{key: string, name: string, connector: string, connected: bool}>
     * }
     */
    public function plan(Workflow $workflow, User $user): array
    {
        $samples = $workflow->template?->sample_input ?? [];

        // In canvas order, left to right then top to bottom: how the form
        // and the steps read in the editor.
        $nodes = $workflow->nodes()->get()->sortBy([
            fn (WorkflowNode $a, WorkflowNode $b): int => ($a->position['x'] ?? 0) <=> ($b->position['x'] ?? 0),
            fn (WorkflowNode $a, WorkflowNode $b): int => ($a->position['y'] ?? 0) <=> ($b->position['y'] ?? 0),
        ]);

        $fields = $nodes
            ->where('type', WorkflowInputNode::TYPE)
            ->map(fn (WorkflowNode $node): array => [
                'key' => $node->key,
                'label' => (string) ($node->config['label'] ?? $node->key),
                'type' => (string) ($node->config['input_type'] ?? 'string'),
                'required' => (bool) ($node->config['required'] ?? false),
                'description' => $node->config['description'] ?? null,
                'sample' => isset($samples[$node->key]) ? (string) $samples[$node->key] : null,
            ])
            ->values()
            ->all();

        $appSteps = $nodes
            ->filter(fn (WorkflowNode $node): bool => $this->registry->has($node->type) && $this->registry->isAppNode($this->registry->resolve($node->type)))
            ->map(function (WorkflowNode $node) use ($workflow, $user): array {
                $contract = $this->registry->resolve($node->type);

                return [
                    'key' => $node->key,
                    'name' => $contract->name(),
                    'connector' => $contract->category(),
                    'connected' => $this->isConnected($contract->category(), $workflow->workspace_id, $user),
                ];
            })
            ->values()
            ->all();

        return ['fields' => $fields, 'app_steps' => $appSteps];
    }

    /**
     * Starts a sample run. A workflow that has never been published (one
     * just made from a template) is published first, since every run is
     * pinned to a version; a published workflow runs its live version.
     *
     * @param  array<string, mixed>  $input
     */
    public function start(Workflow $workflow, User $user, array $input): Run
    {
        if ($workflow->current_version_id === null) {
            $workflow->publishVersion(publisher: $user);
            $workflow->refresh();
        }

        return $this->startWorkflowRun->execute($workflow, $input, $user, self::TRIGGER_TYPE);
    }

    /**
     * Whether the run, or a run it was started by, is a sample run.
     */
    public function isSample(Run $run): bool
    {
        $current = $run;

        for ($depth = 0; $current !== null && $depth <= self::MAX_ANCESTORS; $depth++) {
            if ($current->trigger_type === self::TRIGGER_TYPE) {
                return true;
            }

            $current = $current->parent_run_id === null ? null : Run::query()->find($current->parent_run_id);
        }

        return false;
    }

    /**
     * Whether a sample run shows this step instead of executing it.
     *
     * @param  array<string, mixed>  $config
     */
    public function previews(NodeContract $node, array $config): bool
    {
        return $this->registry->isAppNode($node)
            || ($node instanceof DeclaresEffect && in_array($node->effect($config), [ActionEffect::External, ActionEffect::Destructive], true));
    }

    /**
     * A previewed step's output: what it would have sent, without the
     * account it would have used.
     *
     * @param  array<string, mixed>  $config
     * @return array{sample: true, would_send: array<string, mixed>}
     */
    public function previewOutput(array $config): array
    {
        return [
            'sample' => true,
            'would_send' => array_diff_key($config, array_flip(self::ACCOUNT_FIELDS)),
        ];
    }

    /**
     * Whether the user could run this app's steps for real: a team account
     * in the workspace, or a personal one of theirs.
     */
    private function isConnected(string $connectorKey, string $workspaceId, User $user): bool
    {
        $connectorId = Connector::query()->where('key', $connectorKey)->value('id');

        if ($connectorId === null) {
            return false;
        }

        return ConnectorCredential::query()
            ->where('workspace_id', $workspaceId)
            ->where('connector_id', $connectorId)
            ->where(fn ($query) => $query
                ->where('scope', ConnectorCredentialScope::Team->value)
                ->orWhere(fn ($query) => $query
                    ->where('scope', ConnectorCredentialScope::Personal->value)
                    ->where('created_by', $user->id)))
            ->exists();
    }
}
