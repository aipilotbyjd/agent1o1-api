<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ExecutionStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ExecutionResource;
use App\Models\UsageDailySnapshot;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * High-level KPIs for the workspace over the requested window.
     */
    public function overview(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::ExecutionView)) {
            return $denied;
        }

        [$from, $to] = $this->resolveRange($request);

        $workflows = $workspace->workflows();
        $totalWorkflows = (clone $workflows)->count();
        $activeWorkflows = (clone $workflows)->where('is_active', true)->count();

        $windowed = $workspace->executions()
            ->whereBetween('created_at', [$from, $to]);

        $statusCounts = (clone $windowed)
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $byStatus = [];
        foreach (ExecutionStatus::cases() as $status) {
            $byStatus[$status->value] = (int) ($statusCounts[$status->value] ?? 0);
        }

        $executionsInWindow = array_sum($byStatus);
        $succeeded = $byStatus[ExecutionStatus::Completed->value];
        $failed = $byStatus[ExecutionStatus::Failed->value];
        $finished = $succeeded + $failed;

        $creditsConsumed = (int) (clone $windowed)->sum('credits_consumed');
        $avgDurationMs = (int) round((float) (clone $windowed)
            ->whereNotNull('duration_ms')
            ->avg('duration_ms'));

        return $this->successResponse('Dashboard overview retrieved.', [
            'range' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
            ],
            'workflows' => [
                'total' => $totalWorkflows,
                'active' => $activeWorkflows,
                'inactive' => $totalWorkflows - $activeWorkflows,
            ],
            'executions' => [
                'total' => (int) $workspace->executions()->count(),
                'in_range' => $executionsInWindow,
                'succeeded' => $succeeded,
                'failed' => $failed,
                'running' => $byStatus[ExecutionStatus::Running->value] + $byStatus[ExecutionStatus::Pending->value],
                'waiting' => $byStatus[ExecutionStatus::Waiting->value],
                'by_status' => $byStatus,
                'success_rate' => $finished > 0 ? round(($succeeded / $finished) * 100, 2) : 0.0,
                'avg_duration_ms' => $avgDurationMs,
            ],
            'credits' => [
                'consumed_in_range' => $creditsConsumed,
            ],
        ]);
    }

    /**
     * Daily execution / credit trends, sourced from usage snapshots.
     */
    public function trends(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::ExecutionView)) {
            return $denied;
        }

        [$from, $to] = $this->resolveRange($request);

        $snapshots = UsageDailySnapshot::query()
            ->where('workspace_id', $workspace->id)
            ->whereBetween('snapshot_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('snapshot_date')
            ->get()
            ->keyBy(fn (UsageDailySnapshot $s) => $s->snapshot_date->toDateString());

        $series = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $snapshot = $snapshots->get($key);

            $series[] = [
                'date' => $key,
                'executions_total' => (int) ($snapshot->executions_total ?? 0),
                'executions_succeeded' => (int) ($snapshot->executions_succeeded ?? 0),
                'executions_failed' => (int) ($snapshot->executions_failed ?? 0),
                'credits_used' => (int) ($snapshot->credits_used ?? 0),
                'nodes_executed' => (int) ($snapshot->nodes_executed ?? 0),
                'ai_nodes_executed' => (int) ($snapshot->ai_nodes_executed ?? 0),
            ];

            $cursor->addDay();
        }

        return $this->successResponse('Dashboard trends retrieved.', [
            'range' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'series' => $series,
        ]);
    }

    /**
     * Busiest workflows in the window, ranked by execution volume.
     */
    public function topWorkflows(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::ExecutionView)) {
            return $denied;
        }

        [$from, $to] = $this->resolveRange($request);
        $limit = min(max((int) $request->query('limit', 5), 1), 25);

        $rows = $workspace->executions()
            ->whereBetween('created_at', [$from, $to])
            ->select('workflow_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as succeeded', [ExecutionStatus::Completed->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed', [ExecutionStatus::Failed->value])
            ->selectRaw('AVG(duration_ms) as avg_duration_ms')
            ->groupBy('workflow_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->with('workflow:id,name,icon,color,is_active')
            ->get();

        $data = $rows->map(function ($row) {
            $succeeded = (int) $row->succeeded;
            $failed = (int) $row->failed;
            $finished = $succeeded + $failed;

            return [
                'workflow_id' => $row->workflow_id,
                'name' => $row->workflow?->name,
                'icon' => $row->workflow?->icon,
                'color' => $row->workflow?->color,
                'is_active' => (bool) ($row->workflow?->is_active ?? false),
                'executions' => (int) $row->total,
                'succeeded' => $succeeded,
                'failed' => $failed,
                'success_rate' => $finished > 0 ? round(($succeeded / $finished) * 100, 2) : 0.0,
                'avg_duration_ms' => (int) round((float) $row->avg_duration_ms),
            ];
        })->values();

        return $this->successResponse('Top workflows retrieved.', $data->all());
    }

    /**
     * Most recent executions for the activity feed.
     */
    public function recentActivity(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::ExecutionView)) {
            return $denied;
        }

        $limit = min(max((int) $request->query('limit', 10), 1), 50);

        $executions = $workspace->executions()
            ->with('workflow:id,name,icon,color')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->limit($limit)
            ->get();

        return $this->successResponse(
            'Recent activity retrieved.',
            ExecutionResource::collection($executions),
        );
    }

    /**
     * Resolve the reporting window from `from`/`to` or a `days` shorthand.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(Request $request): array
    {
        $to = $request->query('to')
            ? Carbon::parse($request->query('to'))->endOfDay()
            : Carbon::now()->endOfDay();

        if ($request->query('from')) {
            $from = Carbon::parse($request->query('from'))->startOfDay();
        } else {
            $days = min(max((int) $request->query('days', 30), 1), 365);
            $from = $to->copy()->subDays($days - 1)->startOfDay();
        }

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }
}
