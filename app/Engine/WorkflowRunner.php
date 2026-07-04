<?php

namespace App\Engine;

use App\Contracts\Suspendable;
use App\Engine\Execution\ExecutionWriter;
use App\Engine\Execution\NodeRunner;
use App\Engine\Execution\OutputBuffer;
use App\Enums\ExecutionStatus;
use App\Enums\NodeType;
use App\Events\ExecutionCompletedEvent;
use App\Events\ExecutionFailedEvent;
use App\Events\ExecutionStartedEvent;
use App\Events\ExecutionWaitingEvent;
use App\Events\NodeCompletedEvent;
use App\Jobs\ResumeWorkflowJob;
use App\Models\Execution;
use App\Models\ExecutionCheckpoint;
use App\Models\Variable;
use App\Services\Billing\CreditService;
use Illuminate\Support\Facades\Log;
use Throwable;

class WorkflowRunner
{
    public function __construct(
        private readonly NodeRunner $nodeRunner,
        private readonly ExecutionWriter $writer,
        private readonly CreditService $credits,
    ) {}

    public function run(Execution $execution): void
    {
        try {
            $graph = $this->buildGraph($execution);
            $context = $this->buildContext($execution, $graph);

            // Authoritative, idempotent credit metering under a row lock. Charged
            // once per execution before any node runs; throws when out of credits,
            // which the catch block records as a failed execution.
            if ($workspace = $execution->workspace) {
                $this->credits->consume(
                    $workspace,
                    (int) config('billing.credits_per_execution', 1),
                    $execution,
                );
            }

            $execution->update([
                'status' => ExecutionStatus::Running->value,
                'started_at' => now(),
            ]);

            broadcast(new ExecutionStartedEvent($execution));

            $this->executeLoop($execution, $graph, $context);
        } catch (Throwable $e) {
            Log::error('WorkflowRunner::run failed', [
                'execution_id' => $execution->id,
                'error' => $e->getMessage(),
            ]);

            $execution->update([
                'status' => ExecutionStatus::Failed->value,
                'finished_at' => now(),
                'error' => ['message' => $e->getMessage(), 'class' => get_class($e)],
            ]);

            broadcast(new ExecutionFailedEvent($execution, $e->getMessage()));
        }
    }

    public function resume(Execution $execution): void
    {
        try {
            $checkpoint = $execution->checkpoint;

            if (! $checkpoint) {
                throw new \RuntimeException('No checkpoint found for execution '.$execution->id);
            }

            $graph = $this->buildGraph($execution);
            $context = $this->restoreContext($execution, $graph, $checkpoint);

            $execution->update([
                'status' => ExecutionStatus::Running->value,
            ]);

            $this->executeLoop($execution, $graph, $context);
        } catch (Throwable $e) {
            Log::error('WorkflowRunner::resume failed', [
                'execution_id' => $execution->id,
                'error' => $e->getMessage(),
            ]);

            $execution->update([
                'status' => ExecutionStatus::Failed->value,
                'finished_at' => now(),
                'error' => ['message' => $e->getMessage()],
            ]);

            broadcast(new ExecutionFailedEvent($execution, $e->getMessage()));
        }
    }

    private function executeLoop(Execution $execution, WorkflowGraph $graph, WorkflowContext $context): void
    {
        while (true) {
            $readyNodes = $context->popReadyNodes();

            if (empty($readyNodes)) {
                break;
            }

            [$sync, $async, $blocking] = $this->nodeRunner->partition($readyNodes, $graph);

            // Handle blocking (suspendable) nodes first — suspend on the first one found
            foreach ($blocking as $nodeId) {
                $handler = NodeCatalog::handler($graph->getNode($nodeId)['type'] ?? '');

                if (! $handler instanceof Suspendable) {
                    continue;
                }

                $input = NodeInput::build($nodeId, $graph, $context);
                $pause = $handler->pause($input);

                // Nodes that were in the same ready batch but not yet run
                $pendingNodes = array_merge(
                    array_values(array_diff($blocking, [$nodeId])),
                    $sync,
                    $async,
                );

                $this->saveCheckpoint($execution, $context, $nodeId, $pendingNodes);

                $execution->update([
                    'status' => ExecutionStatus::Waiting->value,
                    'wait_token' => $pause->webhookWaitUuid,
                ]);

                broadcast(new ExecutionWaitingEvent($execution, $pause));

                ResumeWorkflowJob::dispatch($execution->id)
                    ->delay($pause->resumeAt);

                return; // Suspend
            }

            // Execute sync nodes
            $syncResults = $this->nodeRunner->runSyncBatch($sync, $graph, $context);

            // Execute async nodes
            $asyncResults = $this->nodeRunner->runAsyncBatch($async, $graph, $context);

            $allResults = array_merge($syncResults, $asyncResults);

            foreach ($allResults as $nodeId => $result) {
                $sequence = $context->nextSequence();

                if ($result->loopItems !== null) {
                    // Fan out: run the downstream subgraph once per item
                    $this->executeLoopBody($execution, $graph, $context, $nodeId, $result);
                } else {
                    $this->writer->record($execution->id, $nodeId, $nodeId, $graph, $result, $sequence);
                    $context->markCompleted($nodeId, $result);
                    broadcast(new NodeCompletedEvent($execution, $nodeId, $result, $sequence));
                }
            }

            // Always flush after each batch so in-flight data is not lost if the worker
            // process is killed between iterations.
            $this->writer->flush();

            // Halt the execution if any node in this batch failed and no TryCatch
            // node is downstream to handle it. When a TryCatch successor exists,
            // markCompleted() already routed the failure there; let the loop continue.
            $failedNodeId = collect($allResults)->search(fn ($r) => $r->isFailed());

            if ($failedNodeId !== false) {
                $caughtByTryCatch = collect($graph->getSuccessors($failedNodeId))
                    ->contains(fn ($succ) => $graph->getNodeType($succ) === NodeType::TryCatch);

                if (! $caughtByTryCatch) {
                    $failedResult = $allResults[$failedNodeId];

                    $execution->update([
                        'status' => ExecutionStatus::Failed->value,
                        'finished_at' => now(),
                        'error' => $failedResult->error ?? ['message' => 'A workflow node failed'],
                    ]);

                    broadcast(new ExecutionFailedEvent($execution, $failedResult->error['message'] ?? 'Node failed'));

                    return;
                }
            }
        }

        // Final flush and cleanup
        $this->writer->flush();
        $context->outputs->cleanup();

        $execution->update([
            'status' => ExecutionStatus::Completed->value,
            'finished_at' => now(),
        ]);

        broadcast(new ExecutionCompletedEvent($execution));
    }

    /**
     * Execute the downstream subgraph of a LoopNode once per item in loopItems.
     * The loop node itself is recorded here; body nodes are recorded with a
     * per-iteration run key so each item gets its own execution_nodes row.
     */
    private function executeLoopBody(
        Execution $execution,
        WorkflowGraph $graph,
        WorkflowContext $outerContext,
        string $loopNodeId,
        NodeResult $loopResult,
    ): void {
        $sequence = $outerContext->nextSequence();
        $this->writer->record($execution->id, $loopNodeId, $loopNodeId, $graph, $loopResult, $sequence);
        broadcast(new NodeCompletedEvent($execution, $loopNodeId, $loopResult, $sequence));

        $bodyNodes = $this->collectReachableNodes($graph, $loopNodeId);

        // Compute local in-degrees for the body: only predecessors within the body
        // or the loop node itself count
        $bodyInDegree = [];
        foreach ($bodyNodes as $bodyNodeId) {
            $count = 0;
            foreach ($graph->getPredecessors($bodyNodeId) as $pred) {
                if ($pred === $loopNodeId || in_array($pred, $bodyNodes, true)) {
                    $count++;
                }
            }
            $bodyInDegree[$bodyNodeId] = $count;
        }

        $totalItems = count($loopResult->loopItems);

        foreach ($loopResult->loopItems as $index => $item) {
            $outerContext->setVariable('loop_current_item', $item);
            $outerContext->setVariable('loop_current_index', $index);

            // Clone in-degrees for this iteration
            $localInDegree = $bodyInDegree;
            $localCompleted = [];

            // Seed the iteration buffer with all outputs already available in the
            // outer context so expressions referencing pre-loop nodes resolve correctly
            $iterBuffer = new OutputBuffer("{$execution->id}::loop_{$index}", []);
            foreach ($outerContext->getCompletedNodes() as $completedId => $completedResult) {
                if ($completedResult->output !== null) {
                    $iterBuffer->store($completedId, $completedResult->output);
                }
            }
            // Override the loop node's slot with the current item output
            $iterBuffer->store($loopNodeId, [
                'item' => $item,
                'index' => $index,
                'total' => $totalItems,
            ]);

            $iterContext = WorkflowContext::forLoopIteration(
                graph: $graph,
                outputs: $iterBuffer,
                executionId: $outerContext->executionId,
                workspaceId: $outerContext->workspaceId,
                variables: $outerContext->getVariables(),
            );

            // Pre-populate completed nodes so buildExpressionContext works for body nodes
            foreach ($outerContext->getCompletedNodes() as $completedId => $completedResult) {
                $iterContext->markBodyNodeCompleted($completedId, $completedResult);
            }
            // Override loop node entry with item-specific result
            $iterContext->markBodyNodeCompleted(
                $loopNodeId,
                NodeResult::completed(['item' => $item, 'index' => $index, 'total' => $totalItems]),
            );

            // Seed the first body nodes whose only predecessor is the loop node
            $readyQueue = [];
            foreach ($graph->getSuccessors($loopNodeId) as $succ) {
                if (! isset($localInDegree[$succ])) {
                    continue;
                }
                $localInDegree[$succ]--;
                if ($localInDegree[$succ] <= 0) {
                    $readyQueue[] = $succ;
                }
            }

            while (! empty($readyQueue)) {
                $batchResults = $this->nodeRunner->runSyncBatch($readyQueue, $graph, $iterContext);
                $readyQueue = [];

                foreach ($batchResults as $bodyNodeId => $bodyResult) {
                    $seq = $outerContext->nextSequence();
                    $runKey = "{$loopNodeId}::{$bodyNodeId}::{$index}";
                    $this->writer->record(
                        $execution->id, $bodyNodeId, $runKey, $graph, $bodyResult, $seq, $index, $loopNodeId,
                    );
                    $iterContext->markBodyNodeCompleted($bodyNodeId, $bodyResult);
                    $localCompleted[$bodyNodeId] = true;

                    broadcast(new NodeCompletedEvent($execution, $bodyNodeId, $bodyResult, $seq));

                    foreach ($graph->getSuccessors($bodyNodeId) as $succ) {
                        if (! isset($localInDegree[$succ])) {
                            continue; // node is outside the loop body
                        }
                        $localInDegree[$succ]--;
                        if ($localInDegree[$succ] <= 0 && ! isset($localCompleted[$succ])) {
                            $readyQueue[] = $succ;
                        }
                    }
                }
            }

            $iterBuffer->cleanup();
        }

        // Mark loop done in the outer context. finalizeLoop propagates in-degrees
        // through body nodes without re-enqueueing them, then activates post-body nodes.
        $outerContext->finalizeLoop(
            loopNodeId: $loopNodeId,
            result: NodeResult::completed([
                'total' => $totalItems,
                'items' => $loopResult->loopItems,
            ]),
            bodyNodes: $bodyNodes,
        );
    }

    /**
     * Collect all node IDs reachable from the immediate successors of $fromNodeId
     * (i.e. the loop body subgraph).
     *
     * @return string[]
     */
    private function collectReachableNodes(WorkflowGraph $graph, string $fromNodeId): array
    {
        $visited = [];
        $queue = $graph->getSuccessors($fromNodeId);

        while (! empty($queue)) {
            $nodeId = array_shift($queue);
            if (in_array($nodeId, $visited, true)) {
                continue;
            }
            $visited[] = $nodeId;
            foreach ($graph->getSuccessors($nodeId) as $succ) {
                if (! in_array($succ, $visited, true)) {
                    $queue[] = $succ;
                }
            }
        }

        return $visited;
    }

    private function buildGraph(Execution $execution): WorkflowGraph
    {
        $version = $execution->workflow->currentVersion;

        if (! $version) {
            throw new \RuntimeException('Workflow has no published version.');
        }

        return WorkflowGraph::compile(
            $version->nodes_data ?? [],
            $version->edges_data ?? [],
        );
    }

    private function buildContext(Execution $execution, WorkflowGraph $graph): WorkflowContext
    {
        $variables = ['trigger_data' => $execution->trigger_data ?? []];

        // Load workspace variables
        $workspaceVars = Variable::where('workspace_id', $execution->workspace_id)->get();
        foreach ($workspaceVars as $var) {
            $variables[$var->key] = $var->is_secret ? decrypt($var->value) : $var->value;
        }

        $buffer = new OutputBuffer(
            $execution->id,
            $graph->downstreamConsumers,
        );

        return new WorkflowContext(
            graph: $graph,
            outputs: $buffer,
            executionId: $execution->id,
            workspaceId: $execution->workspace_id,
            variables: $variables,
        );
    }

    private function restoreContext(Execution $execution, WorkflowGraph $graph, ExecutionCheckpoint $checkpoint): WorkflowContext
    {
        $contextSnapshot = $checkpoint->context_snapshot ?? [];
        $bufferSnapshot = $checkpoint->output_buffer_snapshot ?? [];
        $frontierData = $checkpoint->frontier_snapshot ?? [];

        $buffer = OutputBuffer::fromSnapshot($execution->id, $bufferSnapshot);

        $context = new WorkflowContext(
            graph: $graph,
            outputs: $buffer,
            executionId: $execution->id,
            workspaceId: $execution->workspace_id,
            variables: $contextSnapshot['variables'] ?? [],
        );

        // Restore in-degree tracking and sequence counter from the snapshot so the
        // resumed run continues rather than replaying completed nodes
        $context->restoreState(
            remainingInDegree: $contextSnapshot['remaining_in_degree'] ?? $graph->inDegree,
            nextSequence: $contextSnapshot['next_sequence'] ?? 0,
        );

        // Distinguish new-format frontier (has 'suspended'/'pending' keys) from
        // the legacy flat-array format (empty array in existing tests)
        if (isset($frontierData['suspended'])) {
            $suspendedNodeId = $frontierData['suspended'];
            $pendingNodeIds = $frontierData['pending'] ?? [];
        } else {
            $suspendedNodeId = null;
            $pendingNodeIds = array_values($frontierData);
        }

        // Re-queue nodes that were in the ready batch but not yet executed
        foreach ($pendingNodeIds as $nodeId) {
            $context->requeueReadyNode($nodeId);
        }

        // Complete the suspended node (e.g. WaitNode) using any resume data set
        // by the webhook controller, so its successors are enqueued
        if ($suspendedNodeId !== null) {
            $resumeData = $contextSnapshot['variables']['resume_data'] ?? [];
            $resumeOutput = is_array($resumeData) ? $resumeData : ['data' => $resumeData];
            $context->markCompleted($suspendedNodeId, NodeResult::completed($resumeOutput));
        }

        return $context;
    }

    private function saveCheckpoint(
        Execution $execution,
        WorkflowContext $context,
        string $suspendedNodeId,
        array $pendingNodes,
    ): void {
        ExecutionCheckpoint::updateOrCreate(
            ['execution_id' => $execution->id],
            [
                'context_snapshot' => $context->snapshot(),
                'output_buffer_snapshot' => $context->outputs->snapshot(),
                'frontier_snapshot' => [
                    'suspended' => $suspendedNodeId,
                    'pending' => $pendingNodes,
                ],
            ],
        );
    }
}
