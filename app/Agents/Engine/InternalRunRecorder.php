<?php

namespace App\Agents\Engine;

use App\Agents\Internal\InternalAgent;
use App\Models\InternalAgentRun;
use App\Services\Agent\AgentBudgetService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Persists one row per internal-agent LLM call to `internal_agent_runs`,
 * attributed to the parent run when one is in flight. Strictly
 * best-effort: recording must never break the call it observes.
 */
class InternalRunRecorder
{
    public function __construct(private readonly AgentBudgetService $budgets) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function record(
        InternalAgent $agent,
        ?string $provider,
        ?string $model,
        mixed $response,
        float $startedAt,
        array $options = [],
        ?Throwable $error = null,
    ): void {
        try {
            $usage = $this->extractUsage($response);

            InternalAgentRun::create([
                'name' => $agent::name(),
                'version' => $agent::$version,
                'parent_run_id' => $options['parent_run_id'] ?? null,
                'workspace_id' => $options['workspace_id'] ?? null,
                'provider' => $provider,
                'model' => $model,
                'prompt_tokens' => $usage['prompt_tokens'] ?? null,
                'completion_tokens' => $usage['completion_tokens'] ?? null,
                'total_tokens' => $usage['total_tokens'] ?? null,
                'estimated_cost' => $this->budgets->estimateCost(
                    $model,
                    $usage['prompt_tokens'] ?? null,
                    $usage['completion_tokens'] ?? null,
                ),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'status' => $error === null ? 'completed' : 'failed',
                'error' => $error?->getMessage(),
            ]);
        } catch (Throwable $e) {
            Log::debug('Failed to record internal agent run.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Best-effort token usage extraction — responses expose usage differently
     * across providers, so probe defensively and normalise.
     *
     * @return array<string, int|null>
     */
    private function extractUsage(mixed $response): array
    {
        if (! is_object($response)) {
            return [];
        }

        $usage = property_exists($response, 'usage') ? $response->usage : null;

        if ($usage === null) {
            return [];
        }

        $usage = is_array($usage) ? $usage : (array) $usage;

        $prompt = $usage['prompt_tokens'] ?? $usage['promptTokens'] ?? $usage['input_tokens'] ?? null;
        $completion = $usage['completion_tokens'] ?? $usage['completionTokens'] ?? $usage['output_tokens'] ?? null;
        $total = $usage['total_tokens'] ?? $usage['totalTokens'] ?? null;

        if ($total === null && ($prompt !== null || $completion !== null)) {
            $total = (int) $prompt + (int) $completion;
        }

        return [
            'prompt_tokens' => $prompt !== null ? (int) $prompt : null,
            'completion_tokens' => $completion !== null ? (int) $completion : null,
            'total_tokens' => $total !== null ? (int) $total : null,
        ];
    }
}
