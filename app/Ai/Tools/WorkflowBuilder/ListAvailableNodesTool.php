<?php

namespace App\Ai\Tools\WorkflowBuilder;

use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListAvailableNodesTool implements Tool
{
    public function __construct(public readonly WorkflowBuilderSession $session) {}

    public function name(): string
    {
        return 'list_available_nodes';
    }

    public function description(): Stringable|string
    {
        return "List every node type you can add to the draft: integrations, AI and data nodes, and flow-logic nodes (loop, subflow, wait, human_approval, join_paths). Each entry gives the 'type' to use in add_node, its category, name, and description.";
    }

    public function handle(Request $request): Stringable|string
    {
        $nodes = array_map(fn (array $node): array => [
            'type' => $node['type'],
            'name' => $node['name'],
            'description' => $node['description'],
            'category' => $node['category'],
        ], app(NodeRegistry::class)->placeableTypes());

        return json_encode($nodes, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
