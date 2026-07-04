<?php

namespace App\Jobs;

use App\Agents\AgentRunner;
use App\Models\Agent;
use App\Models\AgentTrigger;
use App\Models\Credential;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

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

    public function handle(AgentRunner $runner): void
    {
        $agent = Agent::with(['toolConfigs', 'skills.references', 'skills.scripts'])->find($this->agentId);

        if (! $agent || ! $agent->is_active) {
            return;
        }

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

        $response = $runner->run($this->message, [
            ...$this->context,
            'agent' => $agent,
            'credentials' => $credentials,
        ]);

        if ($this->triggerId) {
            AgentTrigger::whereKey($this->triggerId)->update(['last_fired_at' => now()]);
        }

        Log::info('Agent run completed.', [
            'agent_id' => $agent->id,
            'trigger_id' => $this->triggerId,
            'response_length' => strlen($response),
        ]);
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('Agent run failed.', [
            'agent_id' => $this->agentId,
            'trigger_id' => $this->triggerId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
