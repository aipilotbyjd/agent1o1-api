<?php

namespace App\Ai\Tools\WorkflowBuilder;

use App\Ai\Tools\WorkflowBuilder\Concerns\ReadsToolArguments;
use App\Enums\Workflows\FlowControlNodeType;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Services\Workflows\NodeOutputShapes;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The output fields a node type is known to produce in this workspace: what
 * a later node can reference as `{{ nodes.<key>.<field> }}`. Types only,
 * learnt from real outputs (see `NodeOutputShapes`); never the values.
 */
class InspectNodeOutputTool implements Tool
{
    use ReadsToolArguments;

    public function __construct(public readonly WorkflowBuilderSession $session) {}

    public function name(): string
    {
        return 'inspect_node_output';
    }

    public function description(): Stringable|string
    {
        return "Get the output fields a node type is known to produce (field names and types), so later nodes can reference them as {{ nodes.<key>.<field> }}. Takes a node 'type', or the 'key' of a node in the draft. If the shape isn't known yet, rely on the node's description and check with dry_run_workflow.";
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->answer(function () use ($request): string {
            $type = $this->optionalStringArgument($request, 'type') ?? '';
            $key = $this->optionalStringArgument($request, 'key');

            if ($type === '' && $key !== null) {
                $node = collect($this->session->currentGraph()['nodes'])->firstWhere('key', $key);

                if ($node === null) {
                    return "There is no node with key [{$key}] in the draft. Call read_draft to see the current nodes.";
                }

                $type = $node['type'];
            }

            if (! app(NodeRegistry::class)->isPlaceable($type)) {
                return "There is no node for type [{$type}]. Use list_available_nodes to see valid types.";
            }

            $flowControl = FlowControlNodeType::tryFrom($type);

            if ($flowControl !== null) {
                return json_encode([
                    'type' => $type,
                    'known' => $flowControl->sampleOutput() !== null,
                    'note' => $flowControl->builderGuide(),
                ], JSON_THROW_ON_ERROR);
            }

            $schema = app(NodeOutputShapes::class)->schemaFor($this->session->workspace, $type);

            if ($schema === null) {
                return json_encode([
                    'type' => $type,
                    'known' => false,
                    'note' => "No {$type} node has produced output in this workspace yet, so its output fields aren't known. Use the node's description to pick fields, and expect dry_run_workflow to list those references as unverified.",
                ], JSON_THROW_ON_ERROR);
            }

            return json_encode(['type' => $type, 'known' => true, 'fields' => $schema], JSON_THROW_ON_ERROR);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->description('The node type, from list_available_nodes.'),
            'key' => $schema->string()->description('Alternatively, the key of a node already in the draft.'),
        ];
    }
}
