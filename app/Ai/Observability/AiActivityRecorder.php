<?php

namespace App\Ai\Observability;

use Laravel\Ai\Events\ProviderFailedOver;
use Laravel\Ai\Events\StepCompleted;
use Laravel\Ai\Events\ToolFailed;
use Laravel\Ai\Events\ToolInvoked;
use Laravel\Ai\Tools\ToolNameResolver;
use Laravel\Pulse\Facades\Pulse;

/**
 * A Pulse recorder (registered in `config/pulse.php`) fed by Laravel AI's
 * own events, so every agent run is measured the same way whichever
 * runtime started it. Shown on the dashboard by `AiActivityCard`.
 *
 * - `ai_step`, per provider:model: how long each generation step took.
 * - `ai_input_tokens` / `ai_cache_read_tokens`, per provider:model: input
 *   tokens and how many of them the prompt cache served.
 * - `ai_tool` / `ai_tool_failure`, per tool name: duration and failures.
 * - `ai_failover`, per provider:model that was failed over from.
 */
class AiActivityRecorder
{
    /**
     * @var list<class-string>
     */
    public array $listen = [
        StepCompleted::class,
        ToolInvoked::class,
        ToolFailed::class,
        ProviderFailedOver::class,
    ];

    public function record(StepCompleted|ToolInvoked|ToolFailed|ProviderFailedOver $event): void
    {
        match (true) {
            $event instanceof StepCompleted => $this->recordStep($event),
            $event instanceof ToolInvoked => Pulse::record('ai_tool', ToolNameResolver::resolve($event->tool), (int) round($event->time))->avg()->max()->count(),
            $event instanceof ToolFailed => Pulse::record('ai_tool_failure', ToolNameResolver::resolve($event->tool))->count(),
            $event instanceof ProviderFailedOver => Pulse::record('ai_failover', $event->provider->name().':'.$event->model)->count(),
        };
    }

    private function recordStep(StepCompleted $event): void
    {
        $key = $event->provider->name().':'.$event->model;
        $usage = $event->response->usage;

        Pulse::record('ai_step', $key, (int) round($event->time))->avg()->max()->count();
        Pulse::record('ai_input_tokens', $key, $usage->inputTokens)->sum();
        Pulse::record('ai_cache_read_tokens', $key, (int) ($usage->cacheReadInputTokens ?? 0))->sum();
    }
}
