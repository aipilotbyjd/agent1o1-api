<?php

namespace App\Engine\Execution;

use App\Engine\NodeResult;
use App\Engine\WorkflowContext;
use App\Engine\WorkflowGraph;
use App\Models\ExecutionNode;
use Illuminate\Support\Str;

class ExecutionWriter
{
    private array $pendingRows = [];

    public function record(
        string $executionId,
        string $nodeId,
        string $nodeRunKey,
        WorkflowGraph $graph,
        NodeResult $result,
        int $sequence,
        ?int $loopIndex = null,
        ?string $parentFrame = null,
    ): void {
        $node = $graph->getNode($nodeId);
        $now = now();

        $this->pendingRows[] = [
            'id' => Str::uuid()->toString(),
            'execution_id' => $executionId,
            'node_id' => $nodeId,
            'node_run_key' => $nodeRunKey,
            'node_type' => $node['type'] ?? '',
            'node_name' => $node['name'] ?? $nodeId,
            'status' => $result->status->value,
            'started_at' => $now,
            'finished_at' => $now,
            'duration_ms' => $result->durationMs,
            'output_data' => $result->output ? json_encode($result->output) : null,
            'error' => $result->error ? json_encode($result->error) : null,
            'sequence' => $sequence,
            'loop_index' => $loopIndex,
            'parent_frame' => $parentFrame,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    public function flush(): int
    {
        if (empty($this->pendingRows)) {
            return 0;
        }

        $rows = $this->pendingRows;
        $this->pendingRows = [];

        ExecutionNode::upsert($rows, ['execution_id', 'node_run_key'], [
            'node_type', 'node_name', 'status', 'finished_at', 'duration_ms',
            'output_data', 'error', 'sequence', 'loop_index', 'parent_frame', 'updated_at',
        ]);

        return count($rows);
    }

    public function flushIfNeeded(WorkflowContext $context): int
    {
        if ($context->shouldFlush()) {
            $written = $this->flush();
            $context->markFlushed();

            return $written;
        }

        return 0;
    }

    public function hasPending(): bool
    {
        return ! empty($this->pendingRows);
    }
}
