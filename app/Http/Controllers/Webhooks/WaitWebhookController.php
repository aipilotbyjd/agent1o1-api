<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\ExecutionStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ResumeWorkflowJob;
use App\Models\Execution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaitWebhookController extends Controller
{
    /**
     * Resume a workflow paused at a Wait node. The posted body is exposed to
     * downstream nodes as the `resume_data` workflow variable.
     */
    public function resume(Request $request, string $token): JsonResponse
    {
        $execution = Execution::query()
            ->where('wait_token', $token)
            ->where('status', ExecutionStatus::Waiting)
            ->first();

        if (! $execution) {
            return $this->errorResponse('No waiting execution found for this token.', 404);
        }

        $payload = $request->all();

        if ($checkpoint = $execution->checkpoint) {
            $snapshot = $checkpoint->context_snapshot ?? [];
            $snapshot['variables'] = array_merge($snapshot['variables'] ?? [], ['resume_data' => $payload]);
            $checkpoint->update(['context_snapshot' => $snapshot]);
        }

        // Consume the token so the webhook can't be replayed.
        $execution->update(['wait_token' => null]);

        ResumeWorkflowJob::dispatch($execution->id);

        return $this->successResponse('Workflow resumed.', [
            'execution_id' => $execution->id,
            'channel' => "private-execution.{$execution->id}",
        ], 202);
    }
}
