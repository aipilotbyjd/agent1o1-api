<?php

namespace App\Services\Workflows;

use App\Actions\Billing\DeductCreditsAction;
use App\Ai\Agents\WorkflowExplanationAgent;
use App\Ai\Agents\WorkflowImprovementAgent;
use App\Ai\Agents\WorkflowNodeConfigAgent;
use App\Ai\Agents\WorkflowNodeSuggestionAgent;
use App\Ai\ResponseUsage;
use App\Ai\Tools\WorkflowBuilder\SubmitNodeConfigTool;
use App\Ai\Tools\WorkflowBuilder\SubmitNodeSuggestionsTool;
use App\Ai\Tools\WorkflowBuilder\SubmitWorkflowExplanationTool;
use App\Ai\Tools\WorkflowBuilder\SubmitWorkflowImprovementsTool;
use App\Ai\ToolSubmission;
use App\Enums\Billing\CreditTransactionType;
use App\Enums\Workflows\FlowControlNodeType;
use App\Jobs\Workflows\ProcessWorkflowBuilderMessageJob;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Services\Ai\ByokProviderRegistrar;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\Billing\CreditGate;
use App\Services\Billing\CreditMeter;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Responses\TextResponse;
use RuntimeException;

/**
 * The builder's one-shot helpers: suggest the next nodes, configure one
 * node, explain the draft, suggest improvements. Unlike a chat turn these
 * never edit the draft: each returns a proposal the client shows and the
 * user applies (by hand, or by asking the assistant).
 *
 * Each is one forced tool call (`ToolSubmission`) with everything the model
 * needs (the draft, the catalog, schemas) already in the prompt, so it is
 * a single cheap round trip rather than an agent loop. Types the model
 * invents and configs that don't match their schema are filtered or flagged
 * here, never passed through. Every call is charged like an agent draft.
 */
class WorkflowBuilderAssistant
{
    public function __construct(
        private readonly NodeRegistry $registry,
        private readonly ConfigSchemaValidator $configValidator,
        private readonly GraphValidator $graphValidator,
        private readonly NodeOutputShapes $outputShapes,
        private readonly ModelCatalogResolver $modelCatalog,
        private readonly ByokProviderRegistrar $byok,
        private readonly CreditGate $creditGate,
        private readonly CreditMeter $meter,
        private readonly DeductCreditsAction $deductCredits,
    ) {}

    /**
     * @return array<int, array{type: string, name: string, reason: string, connect_from: string|null}>
     */
    public function suggestNodes(WorkflowBuilderSession $session, ?string $note = null): array
    {
        $arguments = $this->ask($session, new WorkflowNodeSuggestionAgent, SubmitNodeSuggestionsTool::NAME, implode("\n\n", array_filter([
            $this->draftSection($session),
            $this->catalogSection(),
            $note ? "The user's note: {$note}" : null,
        ])));

        $keys = array_column($session->currentGraph()['nodes'], 'key');

        return collect($arguments['suggestions'] ?? [])
            ->filter(fn (mixed $suggestion): bool => is_array($suggestion) && $this->registry->isPlaceable((string) ($suggestion['type'] ?? '')))
            ->unique('type')
            ->take(5)
            ->map(fn (array $suggestion): array => [
                'type' => $suggestion['type'],
                'name' => $this->registry->describe($suggestion['type'])['name'],
                'reason' => (string) ($suggestion['reason'] ?? ''),
                'connect_from' => in_array($suggestion['connect_from'] ?? null, $keys, true) ? $suggestion['connect_from'] : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{type: string, config: array<string, mixed>, explanation: string, needs_from_user: array<int, string>, errors: array<int, string>}
     *
     * @throws InvalidArgumentException When the type or key doesn't exist.
     */
    public function configureNode(WorkflowBuilderSession $session, string $instruction, ?string $type = null, ?string $key = null): array
    {
        $existing = $key !== null
            ? collect($session->currentGraph()['nodes'])->firstWhere('key', $key)
            : null;

        if ($key !== null && $existing === null) {
            throw new InvalidArgumentException("There is no node with key [{$key}] in the draft.");
        }

        $type ??= $existing['type'] ?? null;

        $node = $type !== null ? $this->registry->describe($type) : null;

        if ($node === null) {
            throw new InvalidArgumentException("There is no node for type [{$type}].");
        }

        $arguments = $this->ask($session, new WorkflowNodeConfigAgent, SubmitNodeConfigTool::NAME, implode("\n\n", array_filter([
            "Node type: {$type} ({$node['name']}): {$node['description']}",
            FlowControlNodeType::tryFrom($type)?->builderGuide(),
            'Config schema: '.json_encode($node['config_schema'], JSON_THROW_ON_ERROR),
            $existing !== null ? "This is the existing node [{$key}]; its current config is: ".json_encode($existing['config'] ?? [], JSON_THROW_ON_ERROR) : null,
            $this->draftSection($session),
            $this->knownOutputsSection($session),
            "What the user wants this node to do: {$instruction}",
        ])));

        $config = json_decode((string) ($arguments['config_json'] ?? ''), true);
        $config = is_array($config) ? $config : [];

        return [
            'type' => $type,
            'config' => $config,
            'explanation' => (string) ($arguments['explanation'] ?? ''),
            'needs_from_user' => array_values(array_map('strval', array_filter((array) ($arguments['needs_from_user'] ?? []), 'is_scalar'))),
            'errors' => $this->configValidator->validate($node['config_schema'], $config),
        ];
    }

    /**
     * @return array{summary: string, steps: array<int, array{key: string, description: string}>}
     */
    public function explain(WorkflowBuilderSession $session): array
    {
        $arguments = $this->ask($session, new WorkflowExplanationAgent, SubmitWorkflowExplanationTool::NAME, $this->draftSection($session));

        $keys = array_column($session->currentGraph()['nodes'], 'key');

        return [
            'summary' => (string) ($arguments['summary'] ?? ''),
            'steps' => collect($arguments['steps'] ?? [])
                ->filter(fn (mixed $step): bool => is_array($step) && in_array($step['key'] ?? null, $keys, true))
                ->map(fn (array $step): array => ['key' => $step['key'], 'description' => (string) ($step['description'] ?? '')])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<int, array{title: string, description: string, priority: string, node_keys: array<int, string>, suggested_type: string|null}>
     */
    public function suggestImprovements(WorkflowBuilderSession $session): array
    {
        $graph = $session->currentGraph();
        $issues = $this->graphValidator->validate($graph['nodes'], $graph['edges']);

        $arguments = $this->ask($session, new WorkflowImprovementAgent, SubmitWorkflowImprovementsTool::NAME, implode("\n\n", [
            $this->draftSection($session),
            'Validator findings: '.($issues === [] ? 'none' : json_encode($issues, JSON_THROW_ON_ERROR)),
            $this->catalogSection(),
        ]));

        $keys = array_column($graph['nodes'], 'key');

        return collect($arguments['improvements'] ?? [])
            ->filter(fn (mixed $improvement): bool => is_array($improvement) && filled($improvement['title'] ?? null))
            ->take(7)
            ->map(fn (array $improvement): array => [
                'title' => (string) $improvement['title'],
                'description' => (string) ($improvement['description'] ?? ''),
                'priority' => in_array($improvement['priority'] ?? null, SubmitWorkflowImprovementsTool::PRIORITIES, true) ? $improvement['priority'] : 'medium',
                'node_keys' => array_values(array_intersect((array) ($improvement['node_keys'] ?? []), $keys)),
                'suggested_type' => $this->registry->isPlaceable((string) ($improvement['suggested_type'] ?? '')) ? $improvement['suggested_type'] : null,
            ])
            ->values()
            ->all();
    }

    /**
     * One forced-tool-call round trip: gate, prompt, charge, and hand back
     * the submitted arguments. Charged only once a usable answer came back:
     * a `ModelSubmissionException` (502) costs nothing, like an agent draft.
     *
     * @return array<string, mixed>
     */
    private function ask(WorkflowBuilderSession $session, Agent $agent, string $toolName, string $prompt): array
    {
        $this->creditGate->assertCanStartRun($session->workspace);

        $startedAt = now();

        /** @var TextResponse $response */
        $response = $agent->prompt($prompt, provider: $this->provider($session));

        $arguments = ToolSubmission::arguments($response, $toolName);

        $this->deductCredits->execute(
            $session->workspace,
            CreditTransactionType::WorkflowBuilder,
            (string) Str::orderedUuid(),
            $this->meter->costForWorkflowBuilder(ResponseUsage::from($response, $startedAt)),
            'Workflow builder assist',
            allowOverdraft: true,
        );

        return $arguments;
    }

    private function draftSection(WorkflowBuilderSession $session): string
    {
        $graph = $session->currentGraph();

        if ($graph['nodes'] === []) {
            return "The workflow \"{$session->title}\" has no nodes yet.";
        }

        return "The workflow \"{$session->title}\":\n".json_encode([
            'nodes' => array_map(fn (array $node): array => [
                'key' => $node['key'],
                'type' => $node['type'],
                'config' => $node['config'] ?? [],
            ], $graph['nodes']),
            'edges' => array_map(fn (array $edge): array => [
                'from' => $edge['from'],
                'to' => $edge['to'],
                'condition' => $edge['condition'] ?? null,
            ], $graph['edges']),
        ], JSON_THROW_ON_ERROR);
    }

    private function catalogSection(): string
    {
        $lines = collect($this->registry->placeableTypes())
            ->map(fn (array $node): string => "- {$node['type']} ({$node['category']}): {$node['name']}; {$node['description']}")
            ->implode("\n");

        return "Node types you can use:\n{$lines}";
    }

    private function knownOutputsSection(WorkflowBuilderSession $session): ?string
    {
        $lines = collect($session->currentGraph()['nodes'])
            ->map(function (array $node) use ($session): ?string {
                $schema = $this->outputShapes->schemaFor($session->workspace, $node['type']);

                return $schema === null ? null : "- nodes.{$node['key']}: ".implode(', ', array_keys($schema));
            })
            ->filter()
            ->implode("\n");

        return $lines === '' ? null : "Known output fields of the draft's nodes:\n{$lines}";
    }

    /**
     * The same model the builder chat runs on (see
     * `ProcessWorkflowBuilderMessageJob::MODEL_CATALOG_SLUG`) on the
     * session owner's own provider key where there is one.
     *
     * @return array<string, string>|null
     */
    private function provider(WorkflowBuilderSession $session): ?array
    {
        try {
            $chain = $this->modelCatalog->providerChain(ProcessWorkflowBuilderMessageJob::MODEL_CATALOG_SLUG);
        } catch (RuntimeException) {
            return null;
        }

        [$provider] = $this->byok->apply($chain, null, $session->workspace_id, $session->user_id);

        return $provider;
    }
}
