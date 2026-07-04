<?php

namespace App\Agents\Tools\Draft;

use App\Models\Node;
use App\Models\WorkflowBuilderSession;
use App\Services\WorkflowBuilder\DraftService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class AddNodeTool implements Tool
{
    public function __construct(private readonly WorkflowBuilderSession $session) {}

    public function description(): Stringable|string
    {
        return 'Add a new node to the workflow draft. Always call inspect_node_schema first to check what config fields are required. Node type must exist in the catalog.';
    }

    public function handle(Request $request): Stringable|string
    {
        $nodeType = $request['type'] ?? null;
        $nodeId = $request['id'] ?? null ?? 'node_'.uniqid();

        if (! Node::query()->where('type', $nodeType)->exists()) {
            return json_encode([
                'error' => "Node type '{$nodeType}' does not exist. Use list_available_nodes to see valid types.",
            ], JSON_THROW_ON_ERROR);
        }

        $node = [
            'id' => $nodeId,
            'type' => $nodeType,
            'name' => $request['name'] ?? null ?? $nodeType,
            'config' => $request['config'] ?? null ?? [],
            'position' => $request['position'] ?? null ?? ['x' => 0, 'y' => 200],
        ];

        app(DraftService::class)->addNode($this->session, $node);

        return json_encode(['success' => true, 'node_id' => $nodeId], JSON_THROW_ON_ERROR);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Unique node ID (e.g. "node_webhook_1"). Leave blank to auto-generate.'),
            'type' => $schema->string()->required()->description('Node type from the catalog (e.g. "webhook_trigger", "slack_message")'),
            'name' => $schema->string()->description('Display name shown on the canvas'),
            'config' => $schema->object()->description('Configuration object matching the node\'s config_schema'),
            'position' => $schema->object([
                'x' => $schema->number()->required()->description('Horizontal position. Increase by ~250 per step left to right.'),
                'y' => $schema->number()->required()->description('Vertical position. Use 200 for the main flow.'),
            ])->description('Position on the canvas'),
        ];
    }
}
