<?php

namespace App\Agents\Internal;

use App\Agents\Tools\InspectNodeSchemaTool;
use App\Agents\Tools\ListAvailableNodesTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0.3)]
#[MaxSteps(10)]
#[Timeout(120)]
class WorkflowBuilderAgent implements Agent, HasStructuredOutput, HasTools
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are a workflow automation expert. Generate a complete workflow definition from a natural-language description.

        Steps:
        1. Call list_available_nodes to discover available node types.
        2. For each node you plan to use, call inspect_node_schema to get required config fields.
        3. Build the workflow with properly configured nodes and edges.
        4. Start with a trigger node (webhook, schedule, or manual trigger).
        5. Position nodes left-to-right with 250px horizontal spacing, y=200.

        Rules:
        - Use only real node types from the catalog.
        - Always check config_schema before setting config values.
        - Each node must have a unique `id` (e.g. "node_webhook_1", "node_slack_2").
        - Set required config fields — leave optional fields empty unless clearly needed.
        PROMPT;
    }

    /**
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [
            new ListAvailableNodesTool,
            new InspectNodeSchemaTool,
        ];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'workflow_name' => $schema->string()->required(),
            'workflow_description' => $schema->string()->required(),
            'nodes' => $schema->array()->items(
                $schema->object([
                    'id' => $schema->string()->required(),
                    'type' => $schema->string()->required(),
                    'name' => $schema->string()->required(),
                    'config' => $schema->object(),
                    'position' => $schema->object([
                        'x' => $schema->number()->required(),
                        'y' => $schema->number()->required(),
                    ]),
                ])
            )->required(),
            'edges' => $schema->array()->items(
                $schema->object([
                    'source' => $schema->string()->required(),
                    'target' => $schema->string()->required(),
                    'source_handle' => $schema->string()->required(),
                    'target_handle' => $schema->string()->required(),
                ])
            )->required(),
        ];
    }
}
