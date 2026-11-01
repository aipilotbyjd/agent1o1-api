<?php

namespace App\Http\Controllers\Api\Internal\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Workflows\DryRunWorkflowRequest;
use App\Http\Requests\Api\Internal\V1\Workflows\ValidateWorkflowRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\DryRunner;
use App\Services\Workflows\GraphValidator;

/**
 * The two pre-flight checks the builder agent has always had as tools
 * (`Ai\Tools\WorkflowBuilder\{ValidateWorkflowTool,DryRunWorkflowTool}`),
 * exposed over HTTP so the human editor can run exactly the same checks
 * against exactly the same services.
 *
 * Both answer 200 with their findings rather than 422: an invalid graph is
 * the *expected* result of asking "is this graph valid yet?", not a rejected
 * request. Publishing is where invalidity becomes an error
 * (`Workflow::publishVersion()`).
 */
class WorkflowDiagnosticsController extends Controller
{
    public function __construct(
        private readonly GraphValidator $validator,
        private readonly DryRunner $dryRunner,
    ) {}

    public function validateGraph(ValidateWorkflowRequest $request, Workspace $workspace, Workflow $workflow)
    {
        $this->requirePermission(Permission::WorkflowManage);
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        $graph = $this->graphFrom($request, $workflow);
        $issues = $this->validator->validate($graph['nodes'], $graph['edges'], $workspace->id);

        return ApiResponse::success([
            'valid' => $issues === [],
            'issues' => $issues,
        ]);
    }

    public function dryRun(DryRunWorkflowRequest $request, Workspace $workspace, Workflow $workflow)
    {
        $this->requirePermission(Permission::WorkflowManage);
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        $graph = $this->graphFrom($request, $workflow);

        return ApiResponse::success([
            'dry_run' => $this->dryRunner->run($graph, $request->validated('input') ?? [], $workspace),
        ]);
    }

    /**
     * A `graph` in the body wins: that's the unsaved canvas. Otherwise the
     * saved draft is checked, via the same accessor `publishVersion()` uses
     * so what's validated is what would be published.
     *
     * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
     */
    private function graphFrom(ValidateWorkflowRequest $request, Workflow $workflow): array
    {
        $graph = $request->validated('graph');

        if ($graph === null) {
            return $workflow->draftGraph();
        }

        return [
            'nodes' => array_map(
                fn (array $node): array => [
                    'key' => $node['key'],
                    'type' => $node['type'],
                    'config' => $node['config'] ?? [],
                ],
                $graph['nodes'] ?? [],
            ),
            'edges' => array_map(
                fn (array $edge): array => [
                    'from' => $edge['from'],
                    'to' => $edge['to'],
                    'condition' => $edge['condition'] ?? null,
                ],
                $graph['edges'] ?? [],
            ),
        ];
    }
}
