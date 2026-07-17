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

        $policy = $this->retryPolicy($node);
        $startTime = hrtime(true);

        // Per-node Loop Mode: when the node is flagged and its incoming data is a
        // list, run the handler once per item and aggregate the outputs. Falls back
        // to a single normal run when no list is available upstream.
        if ($this->isLoopMode($node)) {
            $items = $this->resolveLoopItems($nodeId, $context);
            if ($items !== null) {
                return $this->executeLoopMode($nodeId, $graph, $context, $handler, $policy, $type, $items, $startTime);
            }
        }

        // Resolve the input once: templated config is deterministic against the
        // current context, so every attempt runs against identical input. The
        // resolved config is persisted alongside the result for debugging.
        $input = NodeInput::build($nodeId, $graph, $context, $nodeId);

        [$result, $attempt] = $this->runWithRetry($handler, $input, $policy, $nodeId, $type, $context);

        $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

        return new NodeResult(
            status: $result->status,
            output: $result->output,
            error: $result->error,
            durationMs: $durationMs,
            activeBranches: $result->activeBranches,
            loopItems: $result->loopItems,
            attempt: $attempt,
            input: ['config' => $input->config],
        );
    }

    /**
     * Run a handler under the node's retry policy. Returns the terminal result and
     * the 1-indexed attempt it settled on. Any thrown handler error is captured as
     * a failed result so the retry/backoff loop can decide whether to try again.
     *
     * @param  array{max_attempts:int, backoff:float, multiplier:float, max_backoff:float}  $policy
     * @return array{0: NodeResult, 1: int}
     */
    private function runWithRetry(
        NodeHandler $handler,
        NodeInput $input,
        array $policy,
        string $nodeId,
        string $type,
        WorkflowContext $context,
    ): array {
        $result = null;
        $attempt = 1;

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

        return [$result, min($attempt, $policy['max_attempts'])];
    }

    /** Whether the node opts into per-item Loop Mode (Gumloop-style fan-out). */
    private function isLoopMode(array $node): bool
    {
        return (bool) (data_get($node, 'data.loopMode')
            ?? data_get($node, 'config.loop_mode')
            ?? data_get($node, 'data.values.loop_mode')
            ?? false);
    }

    /**
     * The list a Loop Mode node iterates: the first list found among its
     * predecessor outputs — either the output itself or a common list-valued field
     * (items/data/results/…). Returns null when no list is upstream so the caller
     * falls back to a single normal execution.
     *
     * @return list<mixed>|null
     */
    private function resolveLoopItems(string $nodeId, WorkflowContext $context): ?array
    {
        foreach ($context->gatherInputData($nodeId) as $output) {
            $list = $this->firstList($output);
            if ($list !== null) {
                return $list;
            }
        }

        return null;
    }

    /**
     * Extract an iterable list from a value: the value itself when it's a list, or
     * the first list-valued field (preferring conventional names) when it's a map.
     *
     * @return list<mixed>|null
     */
    private function firstList(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        if (array_is_list($value)) {
            return $value;
        }

        foreach (['items', 'data', 'results', 'rows', 'records', 'body'] as $key) {
            if (isset($value[$key]) && is_array($value[$key]) && array_is_list($value[$key])) {
                return $value[$key];
            }
        }

        foreach ($value as $nested) {
            if (is_array($nested) && array_is_list($nested)) {
                return $nested;
            }
        }

        return null;
    }

    /**
     * Run a Loop Mode node once per item, exposing the current `item`/`index`/`loop`
     * to config expressions, and collect the per-item outputs into a single list
     * result (`{ items: [...], count }`). Fails fast on the first failing iteration,
     * mirroring normal node failure semantics.
     *
     * @param  array{max_attempts:int, backoff:float, multiplier:float, max_backoff:float}  $policy
     * @param  list<mixed>  $items
     */
    private function executeLoopMode(
        string $nodeId,
        WorkflowGraph $graph,
        WorkflowContext $context,
        NodeHandler $handler,
        array $policy,
        string $type,
        array $items,
        float $startTime,
    ): NodeResult {
        $outputs = [];
        $maxAttempt = 1;
        $total = count($items);
        $lastConfig = [];

        foreach ($items as $index => $item) {
            $input = NodeInput::build($nodeId, $graph, $context, $nodeId, [
                'item' => $item,
                'index' => $index,
                'loop' => ['item' => $item, 'index' => $index, 'total' => $total],
            ]);
            $lastConfig = $input->config;

            [$result, $attempt] = $this->runWithRetry($handler, $input, $policy, $nodeId, $type, $context);
            $maxAttempt = max($maxAttempt, $attempt);

            if ($result->isFailed()) {
                return new NodeResult(
                    status: ExecutionNodeStatus::Failed,
                    output: ['items' => $outputs, 'count' => count($outputs)],
                    error: array_merge(
                        $result->error ?? ['message' => 'Loop iteration failed'],
                        ['loop_index' => $index],
                    ),
                    durationMs: (int) ((hrtime(true) - $startTime) / 1_000_000),
                    attempt: $maxAttempt,
                    input: ['config' => $lastConfig],
                );
            }

            $outputs[] = $result->output;
        }

        return new NodeResult(
            status: ExecutionNodeStatus::Completed,
            output: ['items' => $outputs, 'count' => $total],
            durationMs: (int) ((hrtime(true) - $startTime) / 1_000_000),
            attempt: $maxAttempt,
            input: ['config' => $lastConfig],
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
