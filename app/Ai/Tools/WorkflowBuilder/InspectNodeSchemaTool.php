<?php

namespace App\Ai\Tools\WorkflowBuilder;

use App\Ai\Tools\WorkflowBuilder\Concerns\ReadsToolArguments;
use App\Enums\Workflows\FlowControlNodeType;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class InspectNodeSchemaTool implements Tool
{
    use ReadsToolArguments;

    public function __construct(public readonly WorkflowBuilderSession $session) {}

    public function name(): string
    {
        return 'inspect_node_schema';
    }

    public function description(): Stringable|string
    {
        return "Get the config schema for a node type, so you know exactly which fields to set in a node's 'config'. For flow-logic nodes it also explains how to wire them (how_to_wire). Takes the 'type' from list_available_nodes.";
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->answer(function () use ($request): string {
            $type = $this->stringArgument($request, 'type');
            $node = app(NodeRegistry::class)->describe($type);

            if ($node === null) {
                return "No node found with type [{$type}]. Use list_available_nodes to see valid types.";
            }

            // Flow-control nodes are driven by the engine itself, so how they
            // pause, branch and fail isn't obvious from the schema alone.
            $guide = FlowControlNodeType::tryFrom($type)?->builderGuide();

            return json_encode([
                ...$node,
                ...($guide !== null ? ['how_to_wire' => $guide] : []),
            ], JSON_THROW_ON_ERROR);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->description('The node type to inspect, e.g. "call_api" or "router".')->required(),
        ];
    }
}
