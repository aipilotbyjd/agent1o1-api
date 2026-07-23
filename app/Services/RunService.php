<?php

namespace App\Services;

use App\Enums\ExecutionMode;
use App\Enums\ExecutionStatus;
use App\Jobs\ExecuteWorkflowJob;
use App\Models\Agent;
use App\Models\Run;
use App\Models\Trigger;
use App\Models\TriggerEvent;
use App\Models\User;
use App\Models\Workflow;
use Throwable;

/**
 * Single lifecycle surface for the unified Run model. Covers both kinds of run:
 * workflow executions (trigger / retry / cancel, dispatched to the engine) and
 * agent runs (start / step capture / complete / fail, recorded from the agent
 * runtime). Replaces the former ExecutionService + AgentRunRecorder split.
 */
class RunService
{
    // ── Workflow executions ─────────────────────────────────────────────

    /**
     * Start a manual execution of a workflow.
     */
    public function trigger(Workflow $workflow, ?User $user, array $triggerData = [], ExecutionMode $mode = ExecutionMode::Manual): Run
    {
        $run = Run::create([
            'workflow_id' => $workflow->id,
            'workspace_id' => $workflow->workspace_id,
            'status' => ExecutionStatus::Pending,
            'mode' => $mode,
            'triggered_by' => $user?->id,
            'trigger_data' => $triggerData,
        ]);

        $workflow->incrementExecutionCount();

        ExecuteWorkflowJob::dispatch($run->id);

        return $run;
    }

    /**
     * Start an execution from a trigger event (webhook / polling / schedule).
     */
    public function triggerFromEvent(Trigger $trigger, TriggerEvent $event): Run
    {
        $mode = match ($trigger->type) {
            'webhook' => ExecutionMode::Webhook,
            'polling' => ExecutionMode::Polling,
            'scheduled' => ExecutionMode::Scheduled,
            default => ExecutionMode::Manual,
        };

        return $this->trigger($trigger->workflow, null, $event->event_data ?? [], $mode);
    }

    public function retry(Run $run, ?User $user): Run
    {
        $retry = Run::create([
            'workflow_id' => $run->workflow_id,
            'workspace_id' => $run->workspace_id,
            'status' => ExecutionStatus::Pending,
            'mode' => $run->mode,
            'triggered_by' => $user?->id,
            'trigger_data' => $run->trigger_data,
            'parent_execution_id' => $run->id,
            'attempt' => $run->attempt + 1,
        ]);

        // A retry is a fresh, credit-consuming run — count it like any other
        // execution so execution_count stays consistent with success_rate.
        $run->workflow->incrementExecutionCount();

        ExecuteWorkflowJob::dispatch($retry->id);

        return $retry;
    }

    public function cancel(Run $run): Run
    {
        if ($run->status->isTerminal()) {
            return $run;
        }

        $run->update([
            'status' => ExecutionStatus::Cancelled,
            'finished_at' => now(),
        ]);

        return $run->fresh();
    }

    // ── Agent runs ──────────────────────────────────────────────────────

    /**
     * Open an agent run record — one per conversation reply, trigger fire, or
     * manual run — so run history stays consistent and queryable.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function start(Agent $agent, array $attributes = []): Run
    {
        return $agent->runs()->create([
            'workspace_id' => $agent->workspace_id,
            'provider' => $agent->provider,
            'model' => $agent->model,
            'source' => 'conversation',
            'status' => 'running',
            'started_at' => now(),
            ...$attributes,
        ]);
    }

    /**
     * Append a step to a run, auto-incrementing its step number.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function recordStep(Run $run, array $attributes): void
    {
        $run->steps()->create([
            'execution_node_key' => $attributes['execution_node_key'] ?? 'agent',
            'step_number' => ($run->steps()->max('step_number') ?? 0) + 1,
            'action' => $attributes['action'] ?? 'tool_call',
            'tool_name' => $attributes['tool_name'] ?? null,
            'tool_input' => $attributes['tool_input'] ?? null,
            'tool_output' => $attributes['tool_output'] ?? null,
            'llm_reasoning' => $attributes['llm_reasoning'] ?? null,
            'tokens_used' => $attributes['tokens_used'] ?? 0,
            'duration_ms' => $attributes['duration_ms'] ?? 0,
        ]);
    }

    /**
     * @param  array<string, int|null>  $usage
     */
    public function complete(Run $run, string $output, array $usage = [], ?int $durationMs = null): void
    {
        $run->update([
            'status' => 'completed',
            'output' => $output,
            'prompt_tokens' => $usage['prompt_tokens'] ?? null,
            'completion_tokens' => $usage['completion_tokens'] ?? null,
            'total_tokens' => $usage['total_tokens'] ?? null,
            'duration_ms' => $durationMs ?? $this->elapsed($run),
            'finished_at' => now(),
        ]);
    }

    public function fail(Run $run, Throwable|string $error, ?int $durationMs = null): void
    {
        $run->update([
            'status' => 'failed',
            'error' => $error instanceof Throwable ? $error->getMessage() : $error,
            'duration_ms' => $durationMs ?? $this->elapsed($run),
            'finished_at' => now(),
        ]);
    }

    private function elapsed(Run $run): ?int
    {
        return $run->started_at ? (int) $run->started_at->diffInMilliseconds(now()) : null;
    }
}
