<?php

namespace App\Engine\Nodes\Core;

use App\Contracts\NodeHandler;
use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Enums\ExecutionMode;
use App\Enums\ExecutionStatus;
use App\Jobs\ExecuteWorkflowJob;
use App\Models\Execution;
use App\Models\Workflow;

class SubWorkflowNode implements NodeHandler
{
    public function handle(NodeInput $input): NodeResult
    {
        $workflowId = $input->config['workflow_id'] ?? null;

        if (! $workflowId) {
            return NodeResult::failed('Sub-workflow: workflow_id is required');
        }

        $workflow = Workflow::find($workflowId);

        if (! $workflow) {
            return NodeResult::failed("Sub-workflow not found: {$workflowId}");
        }

        $execution = Execution::create([
            'workflow_id' => $workflowId,
            'workspace_id' => $input->executionMeta['workspace_id'],
            'status' => ExecutionStatus::Pending->value,
            'mode' => ExecutionMode::Manual->value,
            'trigger_data' => $input->inputData,
            'parent_execution_id' => $input->executionMeta['execution_id'],
        ]);

        // Dispatch and wait (synchronous sub-workflow)
        ExecuteWorkflowJob::dispatchSync($execution->id);

        $execution->refresh();

        if ($execution->status === ExecutionStatus::Failed) {
            return NodeResult::failed(
                'Sub-workflow failed: '.($execution->error['message'] ?? 'Unknown error'),
            );
        }

        return NodeResult::completed([
            'execution_id' => $execution->id,
            'status' => $execution->status?->value,
            'result' => $execution->result_data,
        ]);
    }
}
