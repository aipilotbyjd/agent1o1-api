<?php

namespace App\Jobs;

use App\Agents\Internal\Reasoning\ErrorDiagnosisAgent;
use App\Models\AiFixSuggestion;
use App\Models\AiGenerationLog;
use App\Models\Execution;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;

class DiagnoseFailedNode implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(
        public readonly string $executionId,
        public readonly string $nodeId,
    ) {
        $this->onQueue('agents');
    }

    public function handle(): void
    {
        $execution = Execution::with(['workflow.currentVersion', 'nodes'])->find($this->executionId);

        if (! $execution) {
            return;
        }

        $node = $execution->nodes->firstWhere('node_id', $this->nodeId);
        $nodeType = $node?->node_type ?? 'unknown';
        $errorMessage = Arr::get($node?->error ?? [], 'message', 'Unknown error');

        $config = collect($execution->workflow?->currentVersion?->nodes_data ?? [])
            ->firstWhere('id', $this->nodeId)['config'] ?? [];

        $agent = new ErrorDiagnosisAgent(
            errorMessage: $errorMessage,
            nodeType: $nodeType,
            nodeConfig: $config,
            inputData: $node?->input_data ?? [],
        );

        $response = $agent->prompt('Diagnose the failure and propose fixes.');

        AiFixSuggestion::create([
            'execution_id' => $execution->id,
            'workspace_id' => $execution->workspace_id,
            'node_id' => $this->nodeId,
            'node_type' => $nodeType,
            'diagnosis' => $response['diagnosis'] ?? '',
            'suggestions' => $response['suggestions'] ?? [],
            'status' => 'pending',
        ]);

        AiGenerationLog::create([
            'workspace_id' => $execution->workspace_id,
            'created_by' => $execution->triggered_by,
            'type' => 'error_diagnosis',
            'prompt_summary' => "Diagnosed node {$this->nodeId} ({$nodeType})",
        ]);
    }
}
