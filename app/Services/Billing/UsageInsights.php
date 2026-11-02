<?php

namespace App\Services\Billing;

use App\Models\Workspaces\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * A workspace's own view of where its credits go (Gumloop's Insights):
 * credits by day, by kind of work, by workflow and agent, and by model, for
 * the last `$days` days. Only credits and counts: what a model costs the
 * platform is never shown to customers.
 */
class UsageInsights
{
    /**
     * @return array<string, mixed>
     */
    public function for(Workspace $workspace, int $days = 30): array
    {
        $days = max(1, min($days, 90));
        $from = CarbonImmutable::today()->subDays($days - 1);

        $transactions = DB::table('credit_transactions')
            ->where('workspace_id', $workspace->id)
            ->where('created_at', '>=', $from);

        $daily = (clone $transactions)
            ->selectRaw('date(created_at) as day, sum(credits) as credits')
            ->groupBy('day')
            ->pluck('credits', 'day');

        $series = [];

        for ($day = $from; $day->lte(CarbonImmutable::today()); $day = $day->addDay()) {
            $series[] = ['date' => $day->toDateString(), 'credits' => (int) ($daily[$day->toDateString()] ?? 0)];
        }

        $byKind = (clone $transactions)
            ->selectRaw('source_type, count(*) as charges, sum(credits) as credits')
            ->groupBy('source_type')
            ->orderByDesc('credits')
            ->get()
            ->map(fn ($row): array => ['kind' => $row->source_type, 'charges' => (int) $row->charges, 'credits' => (int) $row->credits])
            ->all();

        return [
            'days' => $days,
            'total_credits' => array_sum(array_column($series, 'credits')),
            'daily' => $series,
            'by_kind' => $byKind,
            'top_workflows' => $this->topWorkflows($workspace, $from),
            'top_agents' => $this->topAgents($workspace, $from),
            'models' => $this->models($workspace, $from),
        ];
    }

    /**
     * @return list<array{id: string, name: string, runs: int, credits: int}>
     */
    private function topWorkflows(Workspace $workspace, CarbonImmutable $from): array
    {
        return DB::table('credit_transactions')
            ->join('node_runs', 'node_runs.id', '=', 'credit_transactions.source_id')
            ->join('runs', 'runs.id', '=', 'node_runs.run_id')
            ->join('workflows', 'workflows.id', '=', 'runs.workflow_id')
            ->where('credit_transactions.workspace_id', $workspace->id)
            ->where('credit_transactions.source_type', 'node_run')
            ->where('credit_transactions.created_at', '>=', $from)
            ->selectRaw('workflows.id as id, workflows.name as name, count(distinct runs.id) as runs, sum(credit_transactions.credits) as credits')
            ->groupBy('workflows.id', 'workflows.name')
            ->orderByDesc('credits')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => ['id' => $row->id, 'name' => $row->name, 'runs' => (int) $row->runs, 'credits' => (int) $row->credits])
            ->all();
    }

    /**
     * @return list<array{id: string, name: string, turns: int, credits: int}>
     */
    private function topAgents(Workspace $workspace, CarbonImmutable $from): array
    {
        return DB::table('credit_transactions')
            ->join('agent_messages', 'agent_messages.id', '=', 'credit_transactions.source_id')
            ->join('agent_sessions', 'agent_sessions.id', '=', 'agent_messages.agent_session_id')
            ->join('agents', 'agents.id', '=', 'agent_sessions.agent_id')
            ->where('credit_transactions.workspace_id', $workspace->id)
            ->where('credit_transactions.source_type', 'agent_step')
            ->where('credit_transactions.created_at', '>=', $from)
            ->selectRaw('agents.id as id, agents.name as name, count(*) as turns, sum(credit_transactions.credits) as credits')
            ->groupBy('agents.id', 'agents.name')
            ->orderByDesc('credits')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => ['id' => $row->id, 'name' => $row->name, 'turns' => (int) $row->turns, 'credits' => (int) $row->credits])
            ->all();
    }

    /**
     * @return list<array{model: string, calls: int, own_key_calls: int, input_tokens: int, output_tokens: int}>
     */
    private function models(Workspace $workspace, CarbonImmutable $from): array
    {
        return DB::table('ai_call_events')
            ->where('workspace_id', $workspace->id)
            ->where('occurred_at', '>=', $from)
            ->selectRaw('model, count(*) as calls, sum(case when is_byok then 1 else 0 end) as own_key, sum(input_tokens) as input, sum(output_tokens) as output')
            ->groupBy('model')
            ->orderByDesc('calls')
            ->limit(15)
            ->get()
            ->map(fn ($row): array => ['model' => $row->model, 'calls' => (int) $row->calls, 'own_key_calls' => (int) $row->own_key, 'input_tokens' => (int) $row->input, 'output_tokens' => (int) $row->output])
            ->all();
    }
}
