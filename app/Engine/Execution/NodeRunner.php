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

        // Resolve the input once: templated config is deterministic against the
        // current context, so every attempt runs against identical input. The
        // resolved config is persisted alongside the result for debugging.
        $input = NodeInput::build($nodeId, $graph, $context, $nodeId);
        $persistedInput = ['config' => $input->config];

        $policy = $this->retryPolicy($node);
        $startTime = hrtime(true);
        $result = null;

        for ($attempt = 1; $attempt <= $policy['max_attempts']; $attempt++) {
            try {
                $result = $handler->handle($input);
            } catch (Throwable $e) {
                Log::error("Node execution threw: {$nodeId}", [
                    'type' => $type,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                    'execution_id' => $context->executionId,
                ]);

                $result = new NodeResult(
                    status: ExecutionNodeStatus::Failed,
                    error: ['message' => $e->getMessage(), 'class' => get_class($e)],
                );
            }

            // Succeeded (or a non-failure terminal status): stop retrying.
            if (! $result->isFailed()) {
                break;
            }

            // Exhausted attempts: give up and let the run fail.
            if ($attempt >= $policy['max_attempts']) {
                Log::warning("Node failed after {$attempt} attempt(s): {$nodeId}", [
                    'type' => $type,
                    'execution_id' => $context->executionId,
                    'error' => $result->error['message'] ?? null,
                ]);

                break;
            }

            // Transient failure with retries remaining: back off, then retry.
            $delay = $this->backoffDelay($policy, $attempt);
            Log::info("Retrying node {$nodeId} after failure", [
                'attempt' => $attempt,
                'next_attempt' => $attempt + 1,
                'backoff_seconds' => $delay,
                'execution_id' => $context->executionId,
            ]);

            $this->sleepSeconds($delay);
        }

        $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

        return new NodeResult(
            status: $result->status,
            output: $result->output,
            error: $result->error,
            durationMs: $durationMs,
            activeBranches: $result->activeBranches,
            loopItems: $result->loopItems,
            attempt: $attempt > $policy['max_attempts'] ? $policy['max_attempts'] : $attempt,
            input: $persistedInput,
        );
    }

    /**
     * Resolve the effective retry policy for a node: engine defaults overlaid
     * with any per-node overrides under config.retry.
     *
     * @param  array<string,mixed>  $node
     * @return array{max_attempts:int, backoff:float, multiplier:float, max_backoff:float}
     */
    private function retryPolicy(array $node): array
    {
        $defaults = config('engine.node_retry', []);
        $override = WorkflowGraph::configFor($node)['retry'] ?? [];

        return [
            'max_attempts' => max(1, (int) ($override['max_attempts'] ?? $defaults['max_attempts'] ?? 1)),
            'backoff' => max(0, (float) ($override['backoff'] ?? $defaults['backoff'] ?? 0)),
            'multiplier' => max(1, (float) ($override['multiplier'] ?? $defaults['multiplier'] ?? 2)),
            'max_backoff' => max(0, (float) ($override['max_backoff'] ?? $defaults['max_backoff'] ?? 60)),
        ];
    }

    /**
     * Exponential backoff delay (seconds) before the given 1-indexed attempt's
     * retry: backoff * multiplier^(attempt-1), capped at max_backoff.
     *
     * @param  array{backoff:float, multiplier:float, max_backoff:float}  $policy
     */
    private function backoffDelay(array $policy, int $attempt): float
    {
        if ($policy['backoff'] <= 0) {
            return 0.0;
        }

        $delay = $policy['backoff'] * ($policy['multiplier'] ** ($attempt - 1));

        return min($delay, $policy['max_backoff']);
    }

    /**
     * Sleep between retry attempts. Extracted as a seam so tests can exercise
     * the retry loop without real delays (configure backoff to 0).
     */
    protected function sleepSeconds(float $seconds): void
    {
        if ($seconds > 0) {
            usleep((int) round($seconds * 1_000_000));
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
