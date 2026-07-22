<?php

namespace App\Jobs;

use App\Agents\AgentRunner;
use App\Models\Agent;
use App\Models\AgentTrigger;
use App\Models\Credential;
use App\Services\Agent\AgentBudgetService;
use App\Services\Agent\AgentMemoryService;
use App\Services\AgentRunRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunAgentJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 2;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $agentId,
        public readonly string $message,
        public readonly ?string $triggerId = null,
        public readonly array $context = [],
    ) {
        $this->onQueue('agents');
    }

    public function handle(
        AgentRunner $runner,
        AgentRunRecorder $recorder,
        AgentBudgetService $budgets,
        AgentMemoryService $memory,
    ): void {
        $agent = Agent::with(['toolConfigs', 'skills.references', 'skills.scripts'])->find($this->agentId);

        if (! $agent || ! $agent->is_active) {
            return;
        }

        // Cost & rate guardrails (roadmap item 11): skip paused / over-budget agents.
        if ($blockReason = $budgets->blockReason($agent)) {
            Log::warning('Agent run skipped by budget guardrail.', [
                'agent_id' => $agent->id,
                'reason' => $blockReason,
            ]);

            return;
        }

        $run = $recorder->start($agent, [
            'user_id' => $this->context['fired_by'] ?? null,
            'trigger_id' => $this->triggerId,
            'source' => $this->triggerId ? 'trigger' : 'manual',
            'input' => $this->message,
        ]);

        // Only load credentials for node types the agent's tools actually require.
        // Loading the entire workspace credential store would expose unrelated secrets
        // (DB passwords, API keys for other services) to every agent run.
        $neededTypes = $agent->toolConfigs
            ->where('is_enabled', true)
            ->pluck('node_type')
            ->unique()
            ->values()
            ->all();

        $credentials = Credential::query()
            ->where('workspace_id', $agent->workspace_id)
            ->when(! empty($neededTypes), fn ($q) => $q->whereIn('type', $neededTypes))
            ->get()
            ->groupBy('type')
            ->map(fn ($group) => $group->first()->getDecryptedData())
            ->all();

        try {
            $response = $runner->run($this->message, [
                ...$this->context,
                'agent' => $agent,
                'credentials' => $credentials,
                'user_id' => $this->context['fired_by'] ?? null,
                'agent_run_id' => $run->id,
            ]);
        } catch (Throwable $e) {
            $recorder->fail($run, $e);

            throw $e;
        }

        $recorder->complete($run, $response);
        $run->refresh();

        // Cost accounting + budget enforcement (roadmap item 11).
        $budgets->settleRun($agent, $run);

        // Automatic long-horizon memory extraction (roadmap item 4).
        if ($agent->memory_auto_extract) {
            $memory->extractAndStore($agent, $this->message, $response, $this->context['fired_by'] ?? null, $run);
        }

        if ($this->triggerId) {
            AgentTrigger::whereKey($this->triggerId)->update(['last_fired_at' => now()]);
        }

        Log::info('Agent run completed.', [
            'agent_id' => $agent->id,
            'trigger_id' => $this->triggerId,
            'response_length' => strlen($response),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Agent run failed.', [
            'agent_id' => $this->agentId,
            'trigger_id' => $this->triggerId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
