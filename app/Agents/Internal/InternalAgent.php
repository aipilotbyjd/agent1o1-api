<?php

namespace App\Agents\Internal;

use App\Agents\Engine\InternalRunRecorder;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Throwable;

/**
 * Base class for every platform-owned (internal) agent.
 *
 * Subclasses only declare instructions() (and schema() when they return
 * structured output). In exchange, every call made through run() gets, for
 * free:
 *
 *  - provider/model resolution from config('agents.internal') — a per-agent
 *    override, else the caller's provider/model, else the global default;
 *  - best-effort observability: tokens, cost, duration and status recorded to
 *    `internal_agent_runs`, attributed to the parent AgentRun when one is
 *    given, so "hidden" LLM spend is no longer invisible to analytics.
 *
 * Calling ->prompt() directly still works (Promptable), but bypasses
 * recording — prefer run() everywhere inside the platform.
 */
abstract class InternalAgent implements Agent
{
    use Promptable;

    /**
     * Prompt-format version, bumped when instructions change materially so
     * recorded runs can be traced back to the prompt that produced them.
     */
    public static string $version = '1.0';

    /**
     * The registry name of this agent (e.g. "planner").
     */
    public static function name(): string
    {
        return Registry::nameOf(static::class);
    }

    /**
     * Execute this agent and record the call.
     *
     * @param  array{provider?: string|null, model?: string|null, workspace_id?: string|null, parent_run_id?: string|null}  $options
     * @return mixed The raw Laravel AI response (array-accessible for structured output, string-castable for text).
     */
    public function run(string $prompt, array $options = []): mixed
    {
        [$provider, $model] = $this->resolveProviderAndModel($options);

        $recorder = app(InternalRunRecorder::class);
        $startedAt = microtime(true);

        try {
            $response = $this->prompt($prompt, provider: $provider, model: $model);
        } catch (Throwable $e) {
            $recorder->record($this, $provider, $model, null, $startedAt, $options, $e);

            throw $e;
        }

        $recorder->record($this, $provider, $model, $response, $startedAt, $options);

        return $response;
    }

    /**
     * Per-agent config override wins, then the caller's provider/model (so an
     * internal pass runs on the same account/provider as the agent it serves),
     * then the platform-wide internal default.
     *
     * @param  array<string, mixed>  $options
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveProviderAndModel(array $options): array
    {
        $override = config('agents.internal.overrides.'.static::name(), []);
        $defaults = config('agents.internal.defaults', []);

        $provider = $override['provider'] ?? $options['provider'] ?? $defaults['provider'] ?? null;
        $model = $override['model'] ?? $options['model'] ?? $defaults['model'] ?? null;

        return [$provider, $model];
    }
}
