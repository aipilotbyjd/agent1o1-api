<?php

namespace App\Services\WorkflowBuilder;

use App\Agents\Internal\WorkflowBuilderAgent;
use App\Models\AiGenerationLog;
use App\Models\User;
use App\Models\Workflow;
use App\Models\Workspace;
use App\Services\WorkflowService;

class GenerationService
{
    public function __construct(private readonly WorkflowService $workflowService) {}

    /**
     * Generate a workflow definition from a natural-language prompt.
     *
     * @return array{name: string, description: string, nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
     */
    public function generate(Workspace $workspace, string $prompt, ?User $user = null): array
    {
        $response = (new WorkflowBuilderAgent)->prompt($prompt);

        AiGenerationLog::create([
            'workspace_id' => $workspace->id,
            'created_by' => $user?->id,
            'type' => 'workflow_build',
            'prompt_summary' => str($prompt)->limit(200)->toString(),
        ]);

        return [
            'name' => $response['workflow_name'] ?? 'Generated workflow',
            'description' => $response['workflow_description'] ?? null,
            'nodes' => $this->normalizeNodes($response['nodes'] ?? []),
            'edges' => $this->normalizeEdges($response['edges'] ?? []),
        ];
    }

    public function generateAndSave(Workspace $workspace, User $user, string $prompt): Workflow
    {
        $definition = $this->generate($workspace, $prompt, $user);

        return $this->workflowService->create($workspace, $user, $definition);
    }

    /**
     * Ensure all nodes use 'id' (not 'key') and have required fields.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array<string, mixed>>
     */
    private function normalizeNodes(array $nodes): array
    {
        return array_values(array_map(fn (array $node) => [
            'id' => $node['id'] ?? $node['key'] ?? uniqid('node_'),
            'type' => $node['type'] ?? 'transform',
            'name' => $node['name'] ?? 'Node',
            'config' => $node['config'] ?? [],
            'position' => $node['position'] ?? ['x' => 0, 'y' => 0],
        ], $nodes));
    }

    /**
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<int, array<string, mixed>>
     */
    private function normalizeEdges(array $edges): array
    {
        return array_values(array_map(fn (array $edge) => [
            'source' => $edge['source'] ?? null,
            'target' => $edge['target'] ?? null,
            'sourceHandle' => $edge['source_handle'] ?? $edge['sourceHandle'] ?? 'output',
            'targetHandle' => $edge['target_handle'] ?? $edge['targetHandle'] ?? 'input',
        ], $edges));
    }
}
