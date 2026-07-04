<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workflow;
use App\Models\WorkflowContractSnapshot;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowContractController extends Controller
{
    public function index(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowView)) {
            return $denied;
        }

        return $this->successResponse(
            'Contracts retrieved.',
            $workflow->contracts()->with('testRuns')->latest()->get()->toArray(),
        );
    }

    /**
     * Snapshot the current workflow version's structural signature as a contract.
     */
    public function generate(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowUpdate)) {
            return $denied;
        }

        $version = $workflow->currentVersion;

        if (! $version) {
            return $this->errorResponse('Workflow has no version to snapshot.', 422);
        }

        $contract = $workflow->contracts()->create([
            'workspace_id' => $workspace->id,
            'version_id' => $version->id,
            'created_by' => $request->user()->id,
            'input_schema' => $request->input('input_schema'),
            'output_schema' => $request->input('output_schema'),
            'node_signature' => $this->signature($version->nodes_data ?? []),
        ]);

        return $this->successResponse('Contract generated.', $contract->toArray(), 201);
    }

    /**
     * Run the contract against the workflow's current version and record the result.
     */
    public function run(Request $request, Workspace $workspace, Workflow $workflow, WorkflowContractSnapshot $contract): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowView)) {
            return $denied;
        }

        $current = $this->signature($workflow->currentVersion?->nodes_data ?? []);
        $expected = $contract->node_signature ?? [];

        $missing = array_values(array_diff(array_keys($expected), array_keys($current)));
        $added = array_values(array_diff(array_keys($current), array_keys($expected)));
        $passed = $missing === [] && $added === [];

        $run = $contract->testRuns()->create([
            'status' => $passed ? 'passed' : 'failed',
            'results' => ['missing_nodes' => $missing, 'unexpected_nodes' => $added],
        ]);

        return $this->successResponse(
            $passed ? 'Contract test passed.' : 'Contract test failed.',
            $run->toArray(),
            $passed ? 200 : 422,
        );
    }

    /**
     * Build a stable node id => type signature map.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<string, string>
     */
    private function signature(array $nodes): array
    {
        $signature = [];

        foreach ($nodes as $node) {
            if (isset($node['id'])) {
                $signature[$node['id']] = $node['type'] ?? 'unknown';
            }
        }

        return $signature;
    }
}
