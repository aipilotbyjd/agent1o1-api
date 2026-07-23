<?php

namespace App\Agents\Internal\Workflow;

use App\Agents\Internal\InternalAgent;
use App\Agents\Tools\InspectNodeSchemaTool;
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
#[MaxSteps(8)]
#[Timeout(90)]
class WorkflowEnhancementAgent extends InternalAgent implements HasStructuredOutput, HasTools
{
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are a workflow automation expert. Analyze the given workflow and suggest concrete improvements.

        Look for:
        - Missing error handling (TryCatch nodes around risky operations)
        - No retry logic for flaky external API calls
        - Missing data validation before processing
        - Notification nodes for completion or failure
        - Logging/audit nodes for important state changes
        - Opportunities to parallelize sequential steps
        - Security improvements (credentials, data masking)

        Use list_available_nodes to confirm node types exist before suggesting them.
        Return 3-7 actionable suggestions, most impactful first.
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
            'suggestions' => $schema->array()->items(
                $schema->object([
                    'title' => $schema->string()->required(),
                    'description' => $schema->string()->required(),
                    'impact' => $schema->string()->required(),
                    'priority' => $schema->string()->required(),
                    'effort' => $schema->string()->required(),
                    'suggested_node_type' => $schema->string(),
                ])
            )->required(),
        ];
    }
}
