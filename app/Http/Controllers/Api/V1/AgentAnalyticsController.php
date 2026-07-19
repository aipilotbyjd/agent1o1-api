<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Aggregate usage analytics for a single agent, derived from its run history.
 */
class AgentAnalyticsController extends Controller
{
    public function show(Request $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            return $denied;
        }

        $from = $request->query('from') ? Carbon::parse($request->query('from'))->startOfDay() : now()->subDays(30)->startOfDay();
        $to = $request->query('to') ? Carbon::parse($request->query('to'))->endOfDay() : now()->endOfDay();

        $base = $agent->runs()->whereBetween('created_at', [$from, $to]);
        $dayExpr = $this->dayExpression($agent);

        $totals = (clone $base)
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when status = 'completed' then 1 else 0 end) as completed")
            ->selectRaw("sum(case when status = 'failed' then 1 else 0 end) as failed")
            ->selectRaw("sum(case when status = 'running' then 1 else 0 end) as running")
            ->selectRaw('coalesce(sum(total_tokens), 0) as total_tokens')
            ->selectRaw('coalesce(sum(prompt_tokens), 0) as prompt_tokens')
            ->selectRaw('coalesce(sum(completion_tokens), 0) as completion_tokens')
            ->selectRaw('coalesce(round(avg(duration_ms)), 0) as avg_duration_ms')
            ->selectRaw('coalesce(max(duration_ms), 0) as max_duration_ms')
            ->first();

        $finished = (int) $totals->completed + (int) $totals->failed;

        $bySource = (clone $base)
            ->select('source', DB::raw('count(*) as count'))
            ->groupBy('source')
            ->pluck('count', 'source');

        $byDay = (clone $base)
            ->selectRaw("{$dayExpr} as day")
            ->selectRaw('count(*) as runs')
            ->selectRaw('coalesce(sum(total_tokens), 0) as tokens')
            ->selectRaw("sum(case when status = 'failed' then 1 else 0 end) as failed")
            ->groupByRaw($dayExpr)
            ->orderByRaw($dayExpr)
            ->get();

        return $this->successResponse('Agent analytics retrieved.', [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'totals' => [
                'total_runs' => (int) $totals->total,
                'completed' => (int) $totals->completed,
                'failed' => (int) $totals->failed,
                'running' => (int) $totals->running,
                'success_rate' => $finished > 0 ? round((int) $totals->completed / $finished, 4) : null,
            ],
            'tokens' => [
                'total' => (int) $totals->total_tokens,
                'prompt' => (int) $totals->prompt_tokens,
                'completion' => (int) $totals->completion_tokens,
                'avg_per_run' => (int) $totals->total > 0 ? (int) round((int) $totals->total_tokens / (int) $totals->total) : 0,
            ],
            'latency' => [
                'avg_duration_ms' => (int) $totals->avg_duration_ms,
                'max_duration_ms' => (int) $totals->max_duration_ms,
            ],
            'by_source' => $bySource,
            'by_day' => $byDay->map(fn ($row) => [
                'day' => Carbon::parse($row->day)->toDateString(),
                'runs' => (int) $row->runs,
                'tokens' => (int) $row->tokens,
                'failed' => (int) $row->failed,
            ]),
        ]);
    }

    /** Portable "truncate created_at to a calendar day" SQL expression. */
    private function dayExpression(Agent $agent): string
    {
        return match ($agent->getConnection()->getDriverName()) {
            'pgsql' => 'created_at::date',
            'sqlsrv' => 'cast(created_at as date)',
            default => 'date(created_at)', // sqlite, mysql, mariadb
        };
    }
}
