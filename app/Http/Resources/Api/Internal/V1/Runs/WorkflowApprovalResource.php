<?php

namespace App\Http\Resources\Api\Internal\V1\Runs;

use App\Models\Workflows\WorkflowApproval;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkflowApproval
 */
class WorkflowApprovalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'run_id' => $this->run_id,
            'node_run_id' => $this->node_run_id,
            'requested_at' => $this->requested_at,
            'decided_at' => $this->decided_at,
            'decided_by' => $this->decided_by,
            'decision' => $this->decision,
            'message' => $this->message,
            // What the person deciding is asked, fixed when the run paused;
            // and what they answered once they have approved.
            'title' => $this->nodeRun->output['approval_form']['title'] ?? null,
            'instructions' => $this->nodeRun->output['approval_form']['instructions'] ?? null,
            'fields' => $this->nodeRun->output['approval_form']['fields'] ?? [],
            'inputs' => $this->nodeRun->output['inputs'] ?? null,
        ];
    }
}
