<?php

namespace App\Engine\Trigger;

use App\Jobs\RunAgentJob;
use App\Models\Trigger;
use App\Models\TriggerEvent;
use App\Services\ExecutionService;
use RuntimeException;

/**
 * Terminal stage of the trigger pipeline: given a guarded TriggerEvent, start
 * the right kind of run for the trigger's target. Workflows go through the
 * execution engine; agents are dispatched to the agent runtime. This is the
 * single seam where the unified trigger pipeline fans out per automation type.
 */
class RunDispatcher
{
    public function __construct(private readonly ExecutionService $executions) {}

    public function dispatch(Trigger $trigger, TriggerEvent $event): void
    {
        match ($trigger->target_type) {
            'workflow' => $this->dispatchWorkflow($trigger, $event),
            'agent' => $this->dispatchAgent($trigger, $event),
            default => throw new RuntimeException(
                "Trigger {$trigger->id} has unknown target type '{$trigger->target_type}'.",
            ),
        };
    }

    private function dispatchWorkflow(Trigger $trigger, TriggerEvent $event): void
    {
        $workflow = $trigger->target;

        if ($workflow === null) {
            throw new RuntimeException("Trigger {$trigger->id} targets a missing workflow.");
        }

        $this->executions->triggerFromEvent($trigger, $event);
    }

    private function dispatchAgent(Trigger $trigger, TriggerEvent $event): void
    {
        $agent = $trigger->target;

        // A deactivated or deleted agent has nothing to run; drop the event
        // rather than queueing a job that would immediately no-op.
        if ($agent === null || ! $agent->is_active) {
            return;
        }

        $data = $event->event_data ?? [];

        $message = $trigger->initial_message
            ?: ($data['body']['message'] ?? $data['message'] ?? json_encode($data));

        RunAgentJob::dispatch($trigger->target_id, (string) $message, $trigger->id, [
            'source' => 'trigger',
            'webhook_payload' => $data,
        ]);
    }
}
