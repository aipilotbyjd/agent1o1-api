<?php

namespace App\Services\Billing;

use App\Enums\Billing\CreditTransactionType;
use App\Enums\NodeCategory;
use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSession;
use App\Models\Ai\AiCallEvent;
use App\Models\Billing\CreditTransaction;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Services\Workflows\NodeRegistry;

/**
 * What one run cost the customer, line by line, the way Gumloop's Chat
 * Details panel shows it: an agent turn as Chat & Reasoning, Tool Calls,
 * Compute and the Orchestration Fee (reasoning on the workspace's own key
 * shown as waived), a workflow run by step and kind of step, plus the models
 * that served it. While a run is still going it shows what has accrued so
 * far.
 */
class RunCostBreakdown
{
    public function __construct(
        private readonly CreditMeter $meter,
        private readonly NodeRegistry $registry,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(Run $run): array
    {
        $lines = $run->runnable instanceof AgentSession ? $this->agentLines($run) : $this->workflowLines($run);

        return [
            'run_id' => $run->id,
            'status' => $run->status->value,
            'in_progress' => ! $run->status->isTerminal(),
            'credits' => array_sum(array_column($lines['lines'], 'credits')),
            'charged_credits' => $lines['charged'],
            'lines' => $lines['lines'],
            'steps' => $lines['steps'] ?? [],
            'models' => $this->models($run),
        ];
    }

    /**
     * @return array{lines: list<array{key: string, label: string, credits: int, waived?: bool}>, charged: int}
     */
    private function agentLines(Run $run): array
    {
        $messageId = $run->output['message_id'] ?? null;
        $message = $messageId ? AgentMessage::query()->find($messageId) : null;
        $breakdown = $this->meter->chatTurnBreakdown($message?->usage);

        $lines = [
            ['key' => 'reasoning', 'label' => 'Chat & Reasoning', 'credits' => $breakdown['reasoning']],
            ['key' => 'tool_calls', 'label' => 'Tool Calls', 'credits' => $breakdown['tool_calls']],
            ['key' => 'compute', 'label' => 'Compute', 'credits' => $breakdown['compute']],
            ['key' => 'orchestration', 'label' => 'Orchestration Fee', 'credits' => $breakdown['orchestration']],
        ];

        if ($breakdown['waived_reasoning'] > 0) {
            $lines[] = ['key' => 'waived_reasoning', 'label' => 'Chat & Reasoning (your API key)', 'credits' => 0, 'waived' => true, 'would_have_cost' => $breakdown['waived_reasoning']];
        }

        $charged = $message === null ? 0 : (int) CreditTransaction::query()
            ->where('source_type', CreditTransactionType::AgentStep)
            ->where('source_id', $message->id)
            ->value('credits');

        return ['lines' => $message === null ? [] : $lines, 'charged' => $charged];
    }

    /**
     * @return array{lines: list<array{key: string, label: string, credits: int}>, steps: list<array<string, mixed>>, charged: int}
     */
    private function workflowLines(Run $run): array
    {
        $nodeRuns = $run->nodeRuns()->with('creditTransaction')->whereNotNull('finished_at')->orderBy('created_at')->get();
        $groups = ['base' => 0, 'ai' => 0, 'other' => 0];
        $steps = [];

        foreach ($nodeRuns as $nodeRun) {
            $credits = $nodeRun->creditTransaction?->credits ?? $this->meter->costForNodeRun($nodeRun);
            $isAi = $this->registry->descriptor($nodeRun->type)?->category === NodeCategory::AiAutomation->value;

            $groups['base'] += min(1, $credits);
            $groups[$isAi ? 'ai' : 'other'] += max(0, $credits - 1);

            $steps[] = [
                'node_run_id' => $nodeRun->id,
                'key' => $nodeRun->key,
                'type' => $nodeRun->type,
                'status' => $nodeRun->status->value,
                'credits' => $credits,
                'charged' => $nodeRun->creditTransaction !== null,
            ];
        }

        return [
            'lines' => [
                ['key' => 'base', 'label' => 'Steps run', 'credits' => $groups['base']],
                ['key' => 'ai', 'label' => 'AI steps', 'credits' => $groups['ai']],
                ['key' => 'other', 'label' => 'Data, web and custom steps', 'credits' => $groups['other']],
            ],
            'steps' => $steps,
            'charged' => (int) $nodeRuns->sum(fn (NodeRun $nodeRun): int => $nodeRun->creditTransaction->credits ?? 0),
        ];
    }

    /**
     * @return list<array{provider: string, model: string, calls: int, input_tokens: int, output_tokens: int, own_key: bool}>
     */
    private function models(Run $run): array
    {
        return AiCallEvent::query()
            ->where('run_id', $run->id)
            ->selectRaw('provider, model, is_byok, count(*) as calls, sum(input_tokens) as input, sum(output_tokens) as output')
            ->groupBy('provider', 'model', 'is_byok')
            ->get()
            ->map(fn ($row): array => [
                'provider' => str_starts_with($row->provider, 'byok-') ? 'your key' : $row->provider,
                'model' => $row->model,
                'calls' => (int) $row->calls,
                'input_tokens' => (int) $row->input,
                'output_tokens' => (int) $row->output,
                'own_key' => (bool) $row->is_byok,
            ])
            ->all();
    }
}
