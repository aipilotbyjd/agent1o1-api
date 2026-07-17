<?php

namespace App\Http\Controllers\Api\V1;

use App\Engine\Execution\NodeRunner;
use App\Engine\Execution\OutputBuffer;
use App\Engine\NodeResult;
use App\Engine\WorkflowContext;
use App\Engine\WorkflowGraph;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class NodeTestController extends Controller
{
    /**
     * Run a single node in isolation with the given parameters and optional
     * upstream input, returning its real resolved input, output, status and
     * timing. This powers the editor's inline "Test Node" as a genuine one-node
     * execution through the same engine a full run uses — not a mock.
     */
    public function test(Request $request, Workspace $workspace, NodeRunner $runner): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::WorkflowExecute)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'node_type' => ['required', 'string', 'max:120'],
            'parameters' => ['sometimes', 'array'],
            // Sample upstream data keyed by source node id: { node_5: { … } }, so
            // the node's real {{ node_5.output.field }} tokens resolve in isolation.
            'input' => ['sometimes', 'nullable', 'array'],
        ]);

        $parameters = $validated['parameters'] ?? [];
        $input = $validated['input'] ?? [];

        // Build a throwaway graph: the node under test plus a seeded placeholder for
        // each provided upstream output, wired in as its predecessors. The node then
        // runs through the real engine (expression resolution, retry, handler
        // dispatch) exactly as it would inside a full workflow.
        $nodes = [['id' => 'test', 'type' => $validated['node_type'], 'name' => 'Test Node', 'config' => $parameters]];
        $edges = [];
        $seeds = [];

        foreach ($input as $sourceId => $output) {
            if (! is_string($sourceId) || $sourceId === '') {
                continue;
            }
            $nodes[] = ['id' => $sourceId, 'type' => 'trigger', 'name' => $sourceId, 'config' => []];
            $edges[] = ['source' => $sourceId, 'target' => 'test'];
            $seeds[$sourceId] = is_array($output) ? $output : ['value' => $output];
        }

        $graph = WorkflowGraph::compile($nodes, $edges);

        $context = new WorkflowContext(
            graph: $graph,
            outputs: new OutputBuffer('node-test-'.Str::uuid(), $graph->downstreamConsumers),
            executionId: 'node-test',
            workspaceId: $workspace->id,
            // Also expose the first upstream payload as trigger/input convenience roots.
            variables: [
                'trigger_data' => $seeds ? reset($seeds) : [],
                'input' => $seeds ? reset($seeds) : [],
            ],
        );

        // Seed each upstream output so both {{ nodes.<id>.output.* }} expressions and
        // the handler's gathered input data resolve.
        foreach ($seeds as $sourceId => $output) {
            $context->markBodyNodeCompleted($sourceId, NodeResult::completed($output));
        }

        $context->popReadyNodes(); // drain any start-node seed

        $start = hrtime(true);

        try {
            $result = $runner->runSync('test', $graph, $context);
        } catch (Throwable $e) {
            return $this->successResponse('Node test failed', [
                'success' => false,
                'output' => null,
                'input' => ['config' => $parameters],
                'error' => $e->getMessage(),
                'duration' => (int) ((hrtime(true) - $start) / 1_000_000),
            ]);
        }

        return $this->successResponse('Node tested', [
            'success' => $result->isSuccess(),
            'output' => $result->output,
            'input' => $result->input,
            'error' => $result->error['message'] ?? null,
            'duration' => (int) ((hrtime(true) - $start) / 1_000_000),
        ]);
    }
}
