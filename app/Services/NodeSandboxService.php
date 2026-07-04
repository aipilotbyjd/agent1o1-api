<?php

namespace App\Services;

use App\Contracts\NodeHandler;
use App\Engine\NodeCatalog;
use App\Engine\NodeInput;
use App\Models\Workspace;
use Throwable;

class NodeSandboxService
{
    /**
     * Execute a single node in isolation and return its result payload.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>|null  $credentials
     * @return array{status: string, output: ?array, error: ?array, duration_ms: int}
     */
    public function run(Workspace $workspace, string $type, array $config, array $input = [], ?array $credentials = null): array
    {
        $handler = NodeCatalog::handler($type);

        if (! $handler instanceof NodeHandler) {
            return [
                'status' => 'failed',
                'output' => null,
                'error' => ['message' => "No handler registered for node type: {$type}"],
                'duration_ms' => 0,
            ];
        }

        $nodeInput = new NodeInput(
            nodeId: 'sandbox',
            nodeType: $type,
            nodeName: 'Sandbox',
            config: $config,
            inputData: $input,
            credentials: $credentials,
            variables: [],
            executionMeta: [
                'execution_id' => 'sandbox',
                'workspace_id' => $workspace->id,
                'sandbox' => true,
            ],
            nodeRunKey: 'sandbox',
        );

        $start = hrtime(true);

        try {
            $result = $handler->handle($nodeInput);

            return [
                'status' => $result->status->value,
                'output' => $result->output,
                'error' => $result->error,
                'duration_ms' => (int) ((hrtime(true) - $start) / 1_000_000),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'failed',
                'output' => null,
                'error' => ['message' => $e->getMessage(), 'class' => get_class($e)],
                'duration_ms' => (int) ((hrtime(true) - $start) / 1_000_000),
            ];
        }
    }
}
