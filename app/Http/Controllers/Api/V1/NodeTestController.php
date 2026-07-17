<?php

namespace App\Http\Controllers\Api\V1;

use App\Engine\Execution\NodeRunner;
use App\Engine\Execution\OutputBuffer;
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
            'input' => ['sometimes', 'nullable', 'array'],
        ]);

        $parameters = $validated['parameters'] ?? [];
        $input = $validated['input'] ?? [];

        // A throwaway one-node graph so the node runs through the real engine
        // (expression resolution, retry policy, handler dispatch) exactly as it
        // would inside a full workflow.
        $graph = WorkflowGraph::compile(
            [['id' => 'test', 'type' => $validated['node_type'], 'name' => 'Test Node', 'config' => $parameters]],
            [],
        );

        $context = new WorkflowContext(
            graph: $graph,
            outputs: new OutputBuffer('node-test-'.Str::uuid(), $graph->downstreamConsumers),
            executionId: 'node-test',
            workspaceId: $workspace->id,
            // Expose the sample upstream payload as both the trigger data and an
            // `input` variable so config tokens can reference it during the test.
            variables: ['trigger_data' => $input, 'input' => $input],
        );
        $context->popReadyNodes(); // drain the start-node seed

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
