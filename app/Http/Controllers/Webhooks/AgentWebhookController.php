<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\RunAgentJob;
use App\Models\AgentTrigger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentWebhookController extends Controller
{
    public function receive(Request $request, string $triggerUuid): JsonResponse
    {
        $trigger = AgentTrigger::query()
            ->where('id', $triggerUuid)
            ->where('type', 'webhook')
            ->where('is_active', true)
            ->first();

        if (! $trigger) {
            return $this->errorResponse('Webhook not found.', 404);
        }

        $agent = $trigger->agent;

        if (! $agent || ! $agent->is_active) {
            return $this->errorResponse('Agent is not available.', 422);
        }

        $message = $trigger->initial_message
            ?? $request->input('message')
            ?? json_encode($request->all());

        RunAgentJob::dispatch($agent->id, $message, $trigger->id, [
            'webhook_payload' => $request->all(),
        ]);

        return $this->successResponse('Webhook received. Agent is processing.', null, 202);
    }
}
