<?php

namespace App\Services\Dashboard;

use App\Enums\Agents\AgentActionStatus;
use App\Enums\Billing\CreditTransactionType;
use App\Enums\RunStatus;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentAction;
use App\Models\Billing\CreditTransaction;
use App\Models\Runs\Run;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowApproval;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\NodeTester;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Every aggregate the dashboard endpoints read. Kept out of the controllers
 * because the overview endpoint composes the same numbers the drill-down
 * endpoints return on their own, and "what counts as a run" must not be
 * answered twice.
 *
 * What counts as a run, everywhere here: top-level runs only
 * (`parent_run_id is null`) with `node_test` excluded. Loop iterations and
 * Loop Mode child runs are real rows in `runs`, so counting them would report
 * a 3-item loop as four runs; single-node tests from the builder are real
 * runs too, and a "12 runs today" tile that moves every time someone taps
 * Test is worse than useless. `RunController::index` draws the same two lines
 * via `exclude_trigger_type`, so the dashboard and the run list agree.
 *
 * Credits are read from `credit_transactions` rather than summed off node
 * runs: the ledger is the billing record, it already carries a
 * `(workspace_id, created_at)` index, and it covers agent turns and evals
 * that never produced a `NodeRun` at all.
 */
final class DashboardMetrics
{
    /**
     * How many entries the "top N" breakdowns return. Five fits the card a
     * dashboard renders these in without a scrollbar.
     */
    private const int TOP_LIMIT = 5;

    /**
     * Run counts for the window, keyed by `RunStatus` value, plus the totals a
     * header row needs. `success_rate` is over *finished* runs only: dividing
     * completed by every run would drag the number down whenever the window
     * happens to catch a run mid-flight, which is noise, not failure.
     *
     * @return array{
     *     total: int,
     *     completed: int,
     *     failed: int,
     *     cancelled: int,
     *     in_flight: int,
     *     success_rate: float|null,
     *     avg_duration_ms: int|null,
     *     by_status: array<string, int>
     * }
     */
    public function runTotals(Workspace $workspace, DashboardWindow $window, ?string $workflowId = null): array
    {
        $byStatus = $this->runs($workspace, $workflowId)
            ->where('runs.created_at', '>=', $window->from)
            ->toBase()
            ->select('status')
            ->selectRaw('count(*) as runs_count')
            ->groupBy('status')
            ->pluck('runs_count', 'status');

        $counts = [];

        foreach (RunStatus::cases() as $status) {
            $counts[$status->value] = (int) $byStatus->get($status->value, 0);
        }

        $completed = $counts[RunStatus::Completed->value];
        $failed = $counts[RunStatus::Failed->value];
        $cancelled = $counts[RunStatus::Cancelled->value];
        $finished = $completed + $failed + $cancelled;
        $total = array_sum($counts);

        return [
            'total' => $total,
            'completed' => $completed,
            'failed' => $failed,
            'cancelled' => $cancelled,
            'in_flight' => $total - $finished,
            'success_rate' => $finished === 0 ? null : round($completed / $finished, 4),
            'avg_duration_ms' => $this->averageRunDurationMs($workspace, $window, $workflowId),
            'by_status' => $counts,
        ];
    }

    /**
     * One point per UTC day in the window, zero-filled. Bucketed by
     * `created_at` (when the run was *asked for*) rather than `finished_at`,
     * so a run started on Monday and still going shows up on Monday instead
     * of nowhere.
     *
     * @return array<int, array{date: string, total: int, completed: int, failed: int}>
     */
    public function runSeries(Workspace $workspace, DashboardWindow $window, ?string $workflowId = null): array
    {
        $rows = $this->runs($workspace, $workflowId)
            ->where('runs.created_at', '>=', $window->from)
            ->toBase()
            ->selectRaw($this->dayBucket('runs.created_at').' as bucket')
            ->selectRaw('count(*) as runs_count')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as completed_count', [RunStatus::Completed->value])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as failed_count', [RunStatus::Failed->value])
            ->groupByRaw($this->dayBucket('runs.created_at'))
            ->get()
            ->keyBy('bucket');

        return array_map(fn (string $date): array => [
            'date' => $date,
            'total' => (int) ($rows->get($date)?->runs_count ?? 0),
            'completed' => (int) ($rows->get($date)?->completed_count ?? 0),
            'failed' => (int) ($rows->get($date)?->failed_count ?? 0),
        ], $window->dateKeys());
    }

    /**
     * The busiest workflows in the window. Agent sessions and evals have no
     * `workflow_id`, so they fall out here by construction rather than by a
     * filter that could drift.
     *
     * @return array<int, array{workflow_id: string, name: string|null, runs: int, failed: int}>
     */
    public function topWorkflowsByRuns(Workspace $workspace, DashboardWindow $window): array
    {
        $rows = $this->runs($workspace)
            ->where('runs.created_at', '>=', $window->from)
            ->whereNotNull('workflow_id')
            ->toBase()
            ->select('workflow_id')
            ->selectRaw('count(*) as runs_count')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as failed_count', [RunStatus::Failed->value])
            ->groupBy('workflow_id')
            ->orderByDesc('runs_count')
            ->limit(self::TOP_LIMIT)
            ->get();

        $names = $this->workflowNames($rows->pluck('workflow_id')->all());

        return $rows->map(fn (object $row): array => [
            'workflow_id' => (string) $row->workflow_id,
            'name' => $names[$row->workflow_id] ?? null,
            'runs' => (int) $row->runs_count,
            'failed' => (int) $row->failed_count,
        ])->all();
    }

    /**
     * Runs that are still going right now, not window-scoped, because a run
     * that has been stuck since last week is exactly the one an operator
     * needs to see on today's dashboard.
     *
     * @return array<string, int>
     */
    public function inFlightByStatus(Workspace $workspace): array
    {
        $counts = $this->runs($workspace)
            ->whereIn('status', RunStatus::inFlight())
            ->toBase()
            ->select('status')
            ->selectRaw('count(*) as runs_count')
            ->groupBy('status')
            ->pluck('runs_count', 'status');

        $inFlight = [];

        foreach (RunStatus::inFlight() as $status) {
            $inFlight[$status->value] = (int) $counts->get($status->value, 0);
        }

        return $inFlight;
    }

    /**
     * Approvals still waiting on a human, workspace-wide. Same reasoning as
     * `inFlightByStatus()`: never window-scoped.
     *
     * @return Builder<WorkflowApproval>
     */
    public function pendingApprovals(Workspace $workspace): Builder
    {
        return WorkflowApproval::query()
            ->whereNull('decided_at')
            ->whereHas('run', fn (Builder $query) => $query->where('workspace_id', $workspace->id));
    }

    /**
     * Agent actions waiting on a person: the agent-side counterpart of
     * `pendingApprovals()`, listed in full by `WorkspaceAgentActionController`.
     */
    public function pendingAgentActions(Workspace $workspace): Builder
    {
        return AgentAction::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', AgentActionStatus::Pending);
    }

    /**
     * Credits charged in the window, zero-filled per UTC day.
     *
     * @return array<int, array{date: string, credits: int, topup_credits: int}>
     */
    public function creditSeries(Workspace $workspace, DashboardWindow $window): array
    {
        $rows = $this->creditTransactions($workspace, $window)
            ->selectRaw($this->dayBucket('credit_transactions.created_at').' as bucket')
            ->selectRaw('sum(credits) as credits')
            ->selectRaw('sum(topup_credits) as topup_credits')
            ->groupByRaw($this->dayBucket('credit_transactions.created_at'))
            ->get()
            ->keyBy('bucket');

        return array_map(fn (string $date): array => [
            'date' => $date,
            'credits' => (int) ($rows->get($date)?->credits ?? 0),
            'topup_credits' => (int) ($rows->get($date)?->topup_credits ?? 0),
        ], $window->dateKeys());
    }

    /**
     * Window spend split by what caused it, keyed by `CreditTransactionType`
     * value: the breakdown that answers "is our bill workflows or agents".
     *
     * @return array{total: int, topup: int, by_source_type: array<string, int>}
     */
    public function creditTotals(Workspace $workspace, DashboardWindow $window): array
    {
        $rows = $this->creditTransactions($workspace, $window)
            ->select('source_type')
            ->selectRaw('sum(credits) as credits')
            ->selectRaw('sum(topup_credits) as topup_credits')
            ->groupBy('source_type')
            ->get();

        $bySourceType = [];

        foreach (CreditTransactionType::cases() as $type) {
            $bySourceType[$type->value] = 0;
        }

        $total = 0;
        $topup = 0;

        foreach ($rows as $row) {
            $bySourceType[(string) $row->source_type] = (int) $row->credits;
            $total += (int) $row->credits;
            $topup += (int) $row->topup_credits;
        }

        return ['total' => $total, 'topup' => $topup, 'by_source_type' => $bySourceType];
    }

    /**
     * The workflows that cost the most in the window. Walks the ledger back to
     * a workflow through `node_runs`: a node run is the only charge that has
     * a workflow behind it, which is why the `source_type` filter is not
     * optional here.
     *
     * @return array<int, array{workflow_id: string, name: string|null, credits: int}>
     */
    public function topWorkflowsByCredits(Workspace $workspace, DashboardWindow $window): array
    {
        $rows = $this->creditTransactions($workspace, $window)
            ->where('credit_transactions.source_type', CreditTransactionType::NodeRun)
            ->join('node_runs', 'node_runs.id', '=', 'credit_transactions.source_id')
            ->join('runs', 'runs.id', '=', 'node_runs.run_id')
            ->whereNotNull('runs.workflow_id')
            ->select('runs.workflow_id')
            ->selectRaw('sum(credit_transactions.credits) as credits')
            ->groupBy('runs.workflow_id')
            ->orderByRaw('sum(credit_transactions.credits) desc')
            ->limit(self::TOP_LIMIT)
            ->get();

        $names = $this->workflowNames($rows->pluck('workflow_id')->all());

        return $rows->map(fn (object $row): array => [
            'workflow_id' => (string) $row->workflow_id,
            'name' => $names[$row->workflow_id] ?? null,
            'credits' => (int) $row->credits,
        ])->all();
    }

    /**
     * The agents that cost the most in the window: the `agent_step`
     * counterpart of `topWorkflowsByCredits()`, walking the ledger back
     * through the `AgentMessage` each charge was billed against. Eval and
     * session-evaluation spend is deliberately left out: it belongs to a test
     * suite, not to production agent traffic.
     *
     * @return array<int, array{agent_id: string, name: string|null, credits: int}>
     */
    public function topAgentsByCredits(Workspace $workspace, DashboardWindow $window): array
    {
        $rows = $this->creditTransactions($workspace, $window)
            ->where('credit_transactions.source_type', CreditTransactionType::AgentStep)
            ->join('agent_messages', 'agent_messages.id', '=', 'credit_transactions.source_id')
            ->join('agent_sessions', 'agent_sessions.id', '=', 'agent_messages.agent_session_id')
            ->select('agent_sessions.agent_id')
            ->selectRaw('sum(credit_transactions.credits) as credits')
            ->groupBy('agent_sessions.agent_id')
            ->orderByRaw('sum(credit_transactions.credits) desc')
            ->limit(self::TOP_LIMIT)
            ->get();

        $names = Agent::query()
            ->withTrashed()
            ->whereIn('id', $rows->pluck('agent_id')->all())
            ->pluck('name', 'id');

        return $rows->map(fn (object $row): array => [
            'agent_id' => (string) $row->agent_id,
            'name' => $names[$row->agent_id] ?? null,
            'credits' => (int) $row->credits,
        ])->all();
    }

    /**
     * The base run query the whole dashboard counts over: see the class
     * docblock for why loop children and node tests are excluded.
     *
     * @return Builder<Run>
     */
    private function runs(Workspace $workspace, ?string $workflowId = null): Builder
    {
        return Run::query()
            ->where('runs.workspace_id', $workspace->id)
            ->whereNull('runs.parent_run_id')
            ->where('runs.trigger_type', '!=', NodeTester::TRIGGER_TYPE)
            ->when($workflowId !== null, fn (Builder $query) => $query->where('runs.workflow_id', $workflowId));
    }

    private function creditTransactions(Workspace $workspace, DashboardWindow $window): QueryBuilder
    {
        return CreditTransaction::query()
            ->where('credit_transactions.workspace_id', $workspace->id)
            ->where('credit_transactions.created_at', '>=', $window->from)
            ->toBase();
    }

    /**
     * Soft-deleted workflows still own their history, so a top-spender row
     * for one must render with a name rather than a bare id.
     *
     * @param  array<int, mixed>  $workflowIds
     * @return array<int, string>
     */
    private function workflowNames(array $workflowIds): array
    {
        return Workflow::query()
            ->withTrashed()
            ->whereIn('id', $workflowIds)
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Mean wall-clock time of the runs that actually finished in the window.
     * Null when nothing has finished, and also on a driver this doesn't know
     * how to subtract two timestamps on: an absent number beats a wrong one.
     */
    private function averageRunDurationMs(Workspace $workspace, DashboardWindow $window, ?string $workflowId): ?int
    {
        $expression = $this->durationMs('runs.started_at', 'runs.finished_at');

        if ($expression === null) {
            return null;
        }

        $average = $this->runs($workspace, $workflowId)
            ->where('runs.created_at', '>=', $window->from)
            ->whereNotNull('started_at')
            ->whereNotNull('finished_at')
            ->toBase()
            ->selectRaw("avg({$expression}) as duration")
            ->value('duration');

        return $average === null ? null : (int) round((float) $average);
    }

    /**
     * A `Y-m-d` string for grouping, per driver. Deliberately a formatted
     * string rather than a date/timestamp: every driver then returns the same
     * PHP type, so the zero-fill can key straight off it.
     */
    private function dayBucket(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "to_char({$column}, 'YYYY-MM-DD')",
            'mysql', 'mariadb' => "date_format({$column}, '%Y-%m-%d')",
            default => "strftime('%Y-%m-%d', {$column})",
        };
    }

    /**
     * Milliseconds between two timestamp columns, per driver. Null for a
     * driver with no known expression: `averageRunDurationMs()` reports no
     * average rather than guessing at one.
     */
    private function durationMs(string $start, string $end): ?string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "extract(epoch from ({$end} - {$start})) * 1000",
            'mysql', 'mariadb' => "timestampdiff(microsecond, {$start}, {$end}) / 1000",
            'sqlite' => "(julianday({$end}) - julianday({$start})) * 86400000",
            default => null,
        };
    }
}
