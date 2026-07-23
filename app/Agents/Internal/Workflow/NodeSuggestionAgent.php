<?php

namespace App\Agents\Internal\Workflow;

use App\Agents\Internal\InternalAgent;
use App\Agents\Tools\ListAvailableNodesTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Stringable;

#[Temperature(0.5)]
#[MaxSteps(5)]
#[Timeout(60)]
class NodeSuggestionAgent extends InternalAgent implements HasStructuredOutput, HasTools
{
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are a workflow automation expert. Given a partial workflow, suggest the 3-5 most useful next nodes.

        Steps:
        1. Use list_available_nodes to see what's available.
        2. Analyze the existing workflow's purpose and structure.
        3. Return suggestions ordered by relevance — most useful first.
        PROMPT;
    }

    /**
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [new ListAvailableNodesTool];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'suggestions' => $schema->array()->items(
                $schema->object([
                    'node_type' => $schema->string()->required(),
                    'node_name' => $schema->string()->required(),
                    'reason' => $schema->string()->required(),
                    'category' => $schema->string()->required(),
                    'complexity' => $schema->string()->required(),
                ])
            )->required(),
        ];
    }
}
