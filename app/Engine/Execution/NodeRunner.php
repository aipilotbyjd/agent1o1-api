<?php

namespace App\Engine\Execution;

use App\Contracts\NodeHandler;
use App\Contracts\Suspendable;
use App\Engine\NodeCatalog;
use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\WorkflowContext;
use App\Engine\WorkflowGraph;
use App\Enums\ExecutionNodeStatus;
use App\Enums\NodeType;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\Log;
use Throwable;

class NodeRunner
{
    private const MAX_ASYNC_CONCURRENCY = 4;

    /**
     * Partition node IDs into [sync, async, blocking] groups.
     *
     * @return array{0: string[], 1: string[], 2: string[]}
     */
    public function partition(array $nodeIds, WorkflowGraph $graph): array
    {
        $sync = [];
        $async = [];
        $blocking = [];

        foreach ($nodeIds as $nodeId) {
            $type = $graph->getNodeType($nodeId);

            if ($type->isSuspendable()) {
                $blocking[] = $nodeId;
            } elseif ($type->isAsync() || NodeCatalog::isAppNode($graph->getNode($nodeId)['type'] ?? '')) {
                $async[] = $nodeId;
            } else {
                $sync[] = $nodeId;
            }
        }

        return [$sync, $async, $blocking];
    }

    /**
     * Execute a single node synchronously.
     */
    public function runSync(string $nodeId, WorkflowGraph $graph, WorkflowContext $context): NodeResult
    {
        return $this->executeNode($nodeId, $graph, $context);
    }

    /**
     * Execute a batch of sync nodes sequentially.
     *
     * @return array<string, NodeResult>
     */
    public function runSyncBatch(array $nodeIds, WorkflowGraph $graph, WorkflowContext $context): array
    {
        $results = [];

        foreach ($nodeIds as $nodeId) {
            $results[$nodeId] = $this->executeNode($nodeId, $graph, $context);
        }

        return $results;
    }

    /**
     * Execute a batch of async nodes. Small batches run inline; larger ones chunk.
     *
     * @return array<string, NodeResult>
     */
    public function runAsyncBatch(array $nodeIds, WorkflowGraph $graph, WorkflowContext $context): array
    {
        if (empty($nodeIds)) {
            return [];
        }

        // For small batches, run inline to avoid process overhead
        if (count($nodeIds) <= 3) {
            return $this->runSyncBatch($nodeIds, $graph, $context);
        }

        $results = [];
        $chunks = array_chunk($nodeIds, self::MAX_ASYNC_CONCURRENCY);

        foreach ($chunks as $chunk) {
            $chunkResults = $this->runConcurrently($chunk, $graph, $context);
            $results = array_merge($results, $chunkResults);
        }

        return $results;
    }

    /**
     * Check if a node handler is suspendable (for blocking classification).
     */
    public function isSuspendable(string $nodeId, WorkflowGraph $graph): bool
    {
        $handler = NodeCatalog::handler($graph->getNode($nodeId)['type'] ?? '');

        return $handler instanceof Suspendable;
    }

    private function executeNode(string $nodeId, WorkflowGraph $graph, WorkflowContext $context): NodeResult
    {
        $node = $graph->getNode($nodeId);
        $type = $node['type'] ?? '';

        // Skip nodes: trigger node is the start, not executed
        if ($type === NodeType::Trigger->value) {
            return NodeResult::completed($context->getVariables()['trigger_data'] ?? []);
        }

        $handler = NodeCatalog::handler($type);

        if (! $handler instanceof NodeHandler) {
            return NodeResult::failed("No handler registered for node type: {$type}");
        }

        $startTime = hrtime(true);

        try {
            $input = NodeInput::build($nodeId, $graph, $context, $nodeId);
            $result = $handler->handle($input);
            $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

            // Attach actual duration
            return new NodeResult(
                status: $result->status,
                output: $result->output,
                error: $result->error,
                durationMs: $durationMs,
                activeBranches: $result->activeBranches,
                loopItems: $result->loopItems,
            );
        } catch (Throwable $e) {
            $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

            Log::error("Node execution failed: {$nodeId}", [
                'type' => $type,
                'error' => $e->getMessage(),
                'execution_id' => $context->executionId,
            ]);

            return new NodeResult(
                status: ExecutionNodeStatus::Failed,
                error: ['message' => $e->getMessage(), 'class' => get_class($e)],
                durationMs: $durationMs,
            );
        }
    }

    private function runConcurrently(array $nodeIds, WorkflowGraph $graph, WorkflowContext $context): array
    {
        // Use Laravel Concurrency if available, otherwise fall back to sequential
        if (class_exists(Concurrency::class)) {
            try {
                $tasks = [];
                foreach ($nodeIds as $nodeId) {
                    $tasks[] = fn () => $this->executeNode($nodeId, $graph, $context);
                }

                $chunkResults = Concurrency::run($tasks);
                $results = [];

                foreach (array_values($nodeIds) as $i => $nodeId) {
                    $results[$nodeId] = $chunkResults[$i] ?? NodeResult::failed('Concurrent execution returned no result');
                }

                return $results;
            } catch (Throwable) {
                // Fall through to sequential
            }
        }

        return $this->runSyncBatch($nodeIds, $graph, $context);
    }
}
