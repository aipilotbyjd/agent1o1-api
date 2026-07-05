<?php

namespace App\Engine;

use App\Engine\Execution\OutputBuffer;
use App\Enums\ExecutionNodeStatus;
use App\Enums\NodeType;
use App\Models\Credential;

class WorkflowContext
{
    private const FLUSH_NODE_COUNT = 25;

    private const FLUSH_INTERVAL_SECONDS = 2;

    private array $remainingInDegree;

    private array $completedNodes = [];

    /** Nodes that have received at least one active (taken-branch) input edge. */
    private array $activatedNodes = [];

    /** Nodes skipped since the last drain, awaiting persistence by the runner. */
    private array $skippedBuffer = [];

    private array $readyQueue = [];

    private array $variables;

    private array $credentials;

    private int $completedSinceFlush = 0;

    private float $lastFlushAt;

    private int $nextSequence = 0;

    public function __construct(
        public readonly WorkflowGraph $graph,
        public readonly OutputBuffer $outputs,
        public readonly string $executionId,
        public readonly string $workspaceId,
        array $variables = [],
        array $credentials = [],
    ) {
        $this->variables = $variables;
        $this->credentials = $credentials;
        $this->remainingInDegree = $graph->inDegree;
        $this->lastFlushAt = microtime(true);

        // Seed ready queue with start nodes
        foreach ($graph->startNodes as $nodeId) {
            $this->readyQueue[$nodeId] = true;
        }
    }

    /**
     * Create a fresh context for a single loop iteration.
     * The caller is responsible for seeding the ready queue and managing in-degrees.
     */
    public static function forLoopIteration(
        WorkflowGraph $graph,
        OutputBuffer $outputs,
        string $executionId,
        string $workspaceId,
        array $variables,
    ): self {
        $ctx = new self(
            graph: $graph,
            outputs: $outputs,
            executionId: $executionId,
            workspaceId: $workspaceId,
            variables: $variables,
        );

        // Clear auto-seeded start nodes — caller controls the entry points
        $ctx->readyQueue = [];

        return $ctx;
    }

    public function markCompleted(string $nodeId, NodeResult $result): void
    {
        $this->completedNodes[$nodeId] = $result;
        $this->completedSinceFlush++;

        // Failed nodes are NOT stored in the OutputBuffer — their error payload is
        // injected by gatherInputData() from $completedNodes (which persists for the
        // lifetime of the execution). This avoids the payload being deleted by the
        // buffer's reference-counting before TryCatch has a chance to read it.
        if ($result->status !== ExecutionNodeStatus::Failed) {
            $this->outputs->store($nodeId, $result->output);
        }

        $this->propagate($nodeId, $result);

        $this->releaseConsumedInputs($nodeId);
    }

    /**
     * Drop one OutputBuffer reference on each predecessor whose output this node
     * consumed. The buffer frees a producer's output when its refcount (initialised
     * to the producer's consumer count) reaches zero.
     */
    private function releaseConsumedInputs(string $nodeId): void
    {
        foreach ($this->graph->getPredecessors($nodeId) as $predecessor) {
            $this->outputs->release($predecessor);
        }
    }

    /**
     * Propagate a node's completion (or skip) to its successors: decrement each
     * successor's in-degree, remember whether it received an active (taken-branch)
     * input, and — once every incoming edge is resolved — either enqueue it or,
     * if no active branch ever reached it, mark it Skipped and cascade the skip.
     *
     * This keeps a join (e.g. Merge) after a Condition alive: the not-taken branch
     * is skip-propagated so the join's in-degree still reaches zero and it runs on
     * the taken branch, instead of stranding the remainder of the graph.
     */
    private function propagate(string $nodeId, NodeResult $result): void
    {
        $isFailed = $result->status === ExecutionNodeStatus::Failed;
        $isSkipped = $result->status === ExecutionNodeStatus::Skipped;

        foreach ($this->graph->getSuccessors($nodeId) as $successor) {
            $this->remainingInDegree[$successor]--;

            // An edge is "active" only when a real completion flows down a taken branch.
            if (! $isFailed && ! $isSkipped && $this->isBranchActive($nodeId, $successor, $result)) {
                $this->activatedNodes[$successor] = true;
            }

            if ($this->remainingInDegree[$successor] > 0) {
                continue;
            }

            if (isset($this->completedNodes[$successor]) || isset($this->readyQueue[$successor])) {
                continue;
            }

            if ($isFailed) {
                // Preserve failure semantics: only TryCatch catches failures; every
                // other successor stops here and the runner halts the execution.
                if ($this->graph->getNodeType($successor) === NodeType::TryCatch) {
                    $this->readyQueue[$successor] = true;
                }

                continue;
            }

            if (! empty($this->activatedNodes[$successor])) {
                $this->readyQueue[$successor] = true;
            } else {
                $this->skipNode($successor);
            }
        }
    }

    /**
     * Mark a node Skipped (no active branch reached it) and cascade the skip to its
     * successors so downstream joins can still resolve their in-degree. Skipped
     * nodes are buffered for the runner to persist via drainSkipped().
     */
    private function skipNode(string $nodeId): void
    {
        $result = NodeResult::skipped();

        $this->completedNodes[$nodeId] = $result;
        $this->skippedBuffer[$nodeId] = $result;

        $this->propagate($nodeId, $result);

        $this->releaseConsumedInputs($nodeId);
    }

    /**
     * Return and clear the nodes skipped since the last drain so the runner can
     * record them in the execution log.
     *
     * @return array<string, NodeResult>
     */
    public function drainSkipped(): array
    {
        $skipped = $this->skippedBuffer;
        $this->skippedBuffer = [];

        return $skipped;
    }

    /**
     * Record a body node completion during loop iteration WITHOUT touching
     * the main graph's in-degree tracking (the loop runner manages that manually).
     */
    public function markBodyNodeCompleted(string $nodeId, NodeResult $result): void
    {
        $this->completedNodes[$nodeId] = $result;
        $this->outputs->store($nodeId, $result->output);
        $this->completedSinceFlush++;
    }

    /**
     * Add a node directly to the ready queue (used when restoring checkpoint state).
     */
    public function requeueReadyNode(string $nodeId): void
    {
        $this->readyQueue[$nodeId] = true;
    }

    /**
     * Restore in-degree and sequence counters from a checkpoint snapshot,
     * clearing auto-seeded start nodes so only the checkpoint frontier drives
     * what runs next.
     */
    public function restoreState(array $remainingInDegree, int $nextSequence): void
    {
        $this->remainingInDegree = $remainingInDegree;
        $this->nextSequence = $nextSequence;
        $this->readyQueue = [];

        // Reconstruct completedNodes from the output buffer so expressions can
        // reference upstream outputs in resumed nodes
        foreach ($this->graph->nodeMap as $nodeId => $node) {
            $output = $this->outputs->get($nodeId);
            if ($output !== null) {
                $this->completedNodes[$nodeId] = NodeResult::completed($output);
            }
        }
    }

    public function popReadyNodes(): array
    {
        $nodes = array_keys($this->readyQueue);
        $this->readyQueue = [];

        return $nodes;
    }

    public function gatherInputData(string $nodeId): array
    {
        $inputs = [];

        foreach ($this->graph->getPredecessors($nodeId) as $predecessorId) {
            $output = $this->outputs->get($predecessorId);

            if ($output !== null) {
                $inputs[$predecessorId] = $output;
            } elseif (isset($this->completedNodes[$predecessorId]) && $this->completedNodes[$predecessorId]->isFailed()) {
                $inputs[$predecessorId] = [
                    '__failed' => true,
                    'error' => $this->completedNodes[$predecessorId]->error,
                ];
            }
        }

        return $inputs;
    }

    public function getCredential(string $nodeId): ?array
    {
        if (! isset($this->credentials[$nodeId])) {
            return null;
        }

        $credential = $this->credentials[$nodeId];

        if ($credential instanceof Credential) {
            return json_decode(decrypt($credential->data), true);
        }

        return $credential;
    }

    public function getVariables(): array
    {
        return $this->variables;
    }

    public function setVariable(string $key, mixed $value): void
    {
        $this->variables[$key] = $value;
    }

    public function getCompletedNodes(): array
    {
        return $this->completedNodes;
    }

    public function buildExpressionContext(): array
    {
        $nodes = [];

        foreach ($this->completedNodes as $nodeId => $result) {
            $node = $this->graph->getNode($nodeId);
            $name = $node['name'] ?? $nodeId;
            $nodes[$name] = ['output' => $result->output ?? []];
            $nodes[$nodeId] = ['output' => $result->output ?? []];
        }

        return [
            'nodes' => $nodes,
            'variables' => $this->variables,
        ];
    }

    public function nextSequence(): int
    {
        return $this->nextSequence++;
    }

    public function shouldFlush(): bool
    {
        return $this->completedSinceFlush >= self::FLUSH_NODE_COUNT
            || (microtime(true) - $this->lastFlushAt) >= self::FLUSH_INTERVAL_SECONDS;
    }

    public function markFlushed(): void
    {
        $this->completedSinceFlush = 0;
        $this->lastFlushAt = microtime(true);
    }

    /**
     * Complete a loop node in the outer context, propagating in-degrees through
     * body nodes without re-enqueueing them. Post-body successors are enqueued
     * normally when their in-degree reaches zero.
     *
     * @param  string[]  $bodyNodes  All node IDs in the loop body subgraph
     */
    public function finalizeLoop(string $loopNodeId, NodeResult $result, array $bodyNodes): void
    {
        $this->completedNodes[$loopNodeId] = $result;
        $this->outputs->store($loopNodeId, $result->output);
        $this->completedSinceFlush++;

        // BFS from loop through body subgraph: decrement in-degrees but never
        // enqueue body nodes (they already ran per-item). Enqueue post-body
        // nodes when their in-degree reaches zero.
        $queue = [$loopNodeId];
        $seen = [$loopNodeId => true];

        while (! empty($queue)) {
            $current = array_shift($queue);
            $currentResult = $this->completedNodes[$current] ?? $result;

            foreach ($this->graph->getSuccessors($current) as $successor) {
                $this->remainingInDegree[$successor]--;

                if (in_array($successor, $bodyNodes, true)) {
                    if (! isset($this->completedNodes[$successor])) {
                        $this->completedNodes[$successor] = NodeResult::completed([]);
                    }
                    if (! isset($seen[$successor])) {
                        $seen[$successor] = true;
                        $queue[] = $successor;
                    }
                } elseif ($this->remainingInDegree[$successor] <= 0 && ! isset($this->completedNodes[$successor])) {
                    if ($this->isBranchActive($current, $successor, $currentResult)) {
                        $this->readyQueue[$successor] = true;
                    }
                }
            }
        }

        $this->outputs->release($loopNodeId);
    }

    public function getResult(string $nodeId): ?NodeResult
    {
        return $this->completedNodes[$nodeId] ?? null;
    }

    public function snapshot(): array
    {
        $completedSummary = [];
        foreach ($this->completedNodes as $id => $result) {
            $completedSummary[$id] = ['status' => $result->status->value];
        }

        return [
            'completed_nodes' => $completedSummary,
            'remaining_in_degree' => $this->remainingInDegree,
            'ready_queue' => array_keys($this->readyQueue),
            'variables' => $this->variables,
            'next_sequence' => $this->nextSequence,
        ];
    }

    private function isBranchActive(string $sourceId, string $targetId, NodeResult $sourceResult): bool
    {
        if ($sourceResult->activeBranches === null) {
            return true;
        }

        $edges = $this->graph->getEdgesFrom($sourceId);

        foreach ($edges as $edge) {
            if ($edge['target'] === $targetId) {
                $handle = $edge['sourceHandle'] ?? null;

                return $handle === null || in_array($handle, $sourceResult->activeBranches, true);
            }
        }

        return false;
    }
}
