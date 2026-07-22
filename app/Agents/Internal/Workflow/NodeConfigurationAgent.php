<?php

namespace App\Agents\Internal\Workflow;

use App\Agents\Internal\InternalAgent;
use App\Agents\Tools\InspectNodeSchemaTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Stringable;

#[Temperature(0.2)]
#[MaxSteps(5)]
#[Timeout(60)]
class NodeConfigurationAgent extends InternalAgent implements HasStructuredOutput, HasTools
{
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are a workflow automation expert. Given a node type and what the user wants it to do,
        generate a sensible configuration for that node.

        Steps:
        1. Call inspect_node_schema to get required and optional fields.
        2. Produce a config object based on the user's intent and the schema.
        3. Only include fields relevant to the request.
        4. Add validation_notes about anything the user needs to supply (credentials, IDs, etc.).
        PROMPT;
    }

    /**
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [new InspectNodeSchemaTool];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'config' => $schema->object()->required(),
            'explanation' => $schema->string()->required(),
            'validation_notes' => $schema->string()->required(),
        ];
    }
}
