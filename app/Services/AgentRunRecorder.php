<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Run;
use Throwable;

/**
 * Centralises the lifecycle of an AgentRun record — start, per-tool step
 * capture, and terminal completion/failure — so the conversation and trigger
 * jobs record consistent, queryable run history.
 */
class AgentRunRecorder
{
    /**
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
