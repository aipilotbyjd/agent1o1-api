<?php

namespace App\Ai\Tools\WorkflowBuilder;

use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Services\Workflows\GraphValidator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ValidateWorkflowTool implements Tool
{
    public function __construct(public readonly WorkflowBuilderSession $session) {}

    public function name(): string
    {
        return 'validate_workflow';
    }

    public function description(): Stringable|string
    {
        return 'Check the current draft for problems that would stop it being published: cycles, unreachable nodes, dangling edges, and missing or mistyped config. Call this before telling the user the workflow is ready.';
    }

    public function handle(Request $request): Stringable|string
    {
        $graph = $this->session->currentGraph();
        $issues = app(GraphValidator::class)->validate($graph['nodes'], $graph['edges']);

        if ($issues === []) {
            return 'The draft is valid and can be published.';
        }

        return json_encode(['valid' => false, 'issues' => $issues], JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
