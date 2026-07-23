<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ExecutionMode;
use App\Enums\ExecutionStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\RunResource;
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
            ->select('runnable_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as succeeded', [ExecutionStatus::Completed->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed', [ExecutionStatus::Failed->value])
            ->selectRaw('AVG(duration_ms) as avg_duration_ms')
            ->groupBy('runnable_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->with('workflow:id,name,icon,color,is_active')
            ->get();

        $data = $rows->map(function ($row) {
            $succeeded = (int) $row->succeeded;
            $failed = (int) $row->failed;
            $finished = $succeeded + $failed;

            return [
                'workflow_id' => $row->runnable_id,
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
            RunResource::collection($executions),
        );
    }

    /**
     * Combined dashboard payload consumed by the frontend admin dashboard.
     *
     * Shape mirrors the frontend `IDashboardData` contract (snake_case,
     * epoch-second timestamps).
     */
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::ExecutionView)) {
            return $denied;
        }

        $days = $this->periodToDays((string) $request->query('period', '7d'));
        $now = Carbon::now();
        $from = $now->copy()->subDays($days - 1)->startOfDay();

        $execs = $workspace->executions();

        // ── Workflows ────────────────────────────────────────────────
        $totalWorkflows = $workspace->workflows()->count();
        $activeWorkflows = $workspace->workflows()->where('is_active', true)->count();

        // ── Execution volume windows ─────────────────────────────────
        $totalToday = (clone $execs)->where('created_at', '>=', $now->copy()->startOfDay())->count();
        $totalWeek = (clone $execs)->where('created_at', '>=', $now->copy()->subDays(6)->startOfDay())->count();
        $totalMonth = (clone $execs)->where('created_at', '>=', $now->copy()->subDays(29)->startOfDay())->count();

        // Live state counts (not windowed).
        $runningExecutions = (clone $execs)
            ->whereIn('status', [ExecutionStatus::Running->value, ExecutionStatus::Pending->value])
            ->count();
        $queuedExecutions = (clone $execs)->where('status', ExecutionStatus::Waiting->value)->count();

        // ── Single windowed pull, aggregated in PHP (DB-portable) ────
        $windowRows = (clone $execs)
            ->where('created_at', '>=', $from)
            ->get(['status', 'mode', 'duration_ms', 'created_at']);

        $byStatusFront = [];
        $byTriggerFront = [];
        $byDay = [];
        $byHour = array_fill(0, 24, 0);
        $durationSum = 0;
        $durationCount = 0;
        $succeeded = 0;
        $failed = 0;

        $cursor = $from->copy();
        $endDay = $now->copy()->startOfDay();
        while ($cursor->lte($endDay)) {
            $byDay[$cursor->toDateString()] = ['total' => 0, 'success' => 0, 'failed' => 0];
            $cursor->addDay();
        }

        foreach ($windowRows as $row) {
            $status = $row->status instanceof ExecutionStatus ? $row->status->value : (string) $row->status;
            $mode = $row->mode instanceof ExecutionMode ? $row->mode->value : (string) $row->mode;

            $frontStatus = $this->mapStatusToFront($status);
            $byStatusFront[$frontStatus] = ($byStatusFront[$frontStatus] ?? 0) + 1;

            $frontTrigger = $this->mapTriggerToFront($mode);
            $byTriggerFront[$frontTrigger] = ($byTriggerFront[$frontTrigger] ?? 0) + 1;

            $dateKey = $row->created_at?->toDateString();
            if ($dateKey !== null && isset($byDay[$dateKey])) {
                $byDay[$dateKey]['total']++;
                if ($status === ExecutionStatus::Completed->value) {
                    $byDay[$dateKey]['success']++;
                } elseif ($status === ExecutionStatus::Failed->value) {
                    $byDay[$dateKey]['failed']++;
                }
            }

            if ($row->created_at !== null) {
                $byHour[(int) $row->created_at->format('G')]++;
            }

            if ($status === ExecutionStatus::Completed->value) {
                $succeeded++;
            } elseif ($status === ExecutionStatus::Failed->value) {
                $failed++;
            }

            if ($row->duration_ms !== null) {
                $durationSum += (int) $row->duration_ms;
                $durationCount++;
            }
        }

        $finished = $succeeded + $failed;
        $successRate = $finished > 0 ? round(($succeeded / $finished) * 100, 2) : 0.0;
        $avgDurationMs = $durationCount > 0 ? (int) round($durationSum / $durationCount) : 0;

        // ── Credentials & schedules ──────────────────────────────────
        $totalCredentials = $workspace->credentials()->count();
        $scheduleTriggers = $workspace->triggers()->whereNotNull('schedule_expression');
        $totalSchedules = (clone $scheduleTriggers)->count();
        $activeSchedules = (clone $scheduleTriggers)
            ->where('is_active', true)
            ->where('is_paused', false)
            ->count();

        $summary = [
            'total_workflows' => $totalWorkflows,
            'active_workflows' => $activeWorkflows,
            'inactive_workflows' => max(0, $totalWorkflows - $activeWorkflows),
            'draft_workflows' => 0,
            'total_executions_today' => $totalToday,
            'total_executions_week' => $totalWeek,
            'total_executions_month' => $totalMonth,
            'success_rate' => $successRate,
            'avg_duration_ms' => $avgDurationMs,
            'total_credentials' => $totalCredentials,
            'total_schedules' => $totalSchedules,
            'active_schedules' => $activeSchedules,
            'running_executions' => $runningExecutions,
            'queued_executions' => $queuedExecutions,
        ];

        // ── Recent executions ────────────────────────────────────────
        $recentExecutions = (clone $execs)
            ->with('workflow:id,name')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'workflow_id' => $e->workflow_id,
                'workflow_name' => $e->workflow?->name,
                'status' => $this->mapStatusToFront($e->status instanceof ExecutionStatus ? $e->status->value : (string) $e->status),
                'trigger_type' => $this->mapTriggerToFront($e->mode instanceof ExecutionMode ? $e->mode->value : (string) $e->mode),
                'duration_ms' => $e->duration_ms !== null ? (int) $e->duration_ms : null,
                'started_at' => $e->started_at?->timestamp,
                'completed_at' => $e->finished_at?->timestamp,
                'created_at' => $e->created_at?->timestamp,
            ])
            ->values()
            ->all();

        // ── Top workflows (by volume in window) ──────────────────────
        $topWorkflows = (clone $execs)
            ->where('created_at', '>=', $from)
            ->select('runnable_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as succeeded', [ExecutionStatus::Completed->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed', [ExecutionStatus::Failed->value])
            ->selectRaw('AVG(duration_ms) as avg_duration_ms')
            ->selectRaw('MAX(created_at) as last_executed_at')
            ->groupBy('runnable_id')
            ->orderByDesc('total')
            ->limit(5)
            ->with('workflow:id,name,is_active')
            ->get()
            ->map(function ($row) {
                $ok = (int) $row->succeeded;
                $fail = (int) $row->failed;
                $fin = $ok + $fail;

                return [
                    'id' => $row->runnable_id,
                    'name' => $row->workflow?->name,
                    'status' => ($row->workflow?->is_active) ? 'active' : 'inactive',
                    'execution_count' => (int) $row->total,
                    'success_count' => $ok,
                    'failed_count' => $fail,
                    'success_rate' => $fin > 0 ? round(($ok / $fin) * 100, 2) : 0.0,
                    'avg_duration_ms' => (int) round((float) $row->avg_duration_ms),
                    'last_executed_at' => $row->last_executed_at ? Carbon::parse($row->last_executed_at)->timestamp : null,
                ];
            })
            ->values()
            ->all();

        // ── Recent failures ──────────────────────────────────────────
        $recentFailures = (clone $execs)
            ->where('status', ExecutionStatus::Failed->value)
            ->with('workflow:id,name')
            ->orderByDesc('finished_at')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function ($e) {
                $error = $e->error;
                $message = is_array($error)
                    ? ($error['message'] ?? 'Execution failed.')
                    : (is_string($error) && $error !== '' ? $error : 'Execution failed.');

                return [
                    'id' => $e->id,
                    'workflow_id' => $e->workflow_id,
                    'workflow_name' => $e->workflow?->name,
                    'error_message' => $message,
                    'error_node_id' => is_array($error) ? ($error['node_id'] ?? null) : null,
                    'failed_at' => ($e->finished_at ?? $e->created_at)?->timestamp,
                ];
            })
            ->values()
            ->all();

        // ── Executions by day (gap-filled) ───────────────────────────
        $executionsByDay = [];
        foreach ($byDay as $date => $counts) {
            $executionsByDay[] = [
                'date' => $date,
                'total' => $counts['total'],
                'success' => $counts['success'],
                'failed' => $counts['failed'],
            ];
        }

        // ── Executions by hour ───────────────────────────────────────
        $executionsByHour = [];
        foreach ($byHour as $hour => $count) {
            $executionsByHour[] = ['hour' => $hour, 'count' => $count];
        }

        // ── Upcoming schedules ───────────────────────────────────────
        $upcomingSchedules = $workspace->triggers()
            ->whereNotNull('schedule_expression')
            ->where('is_active', true)
            ->with('workflow:id,name')
            ->orderBy('schedule_next_run_at')
            ->limit(5)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'workflow_id' => $t->workflow_id,
                'workflow_name' => $t->workflow?->name,
                'cron_expression' => $t->schedule_expression,
                'timezone' => $t->schedule_timezone ?? 'UTC',
                'next_run_at' => $t->schedule_next_run_at?->timestamp,
                'is_active' => (bool) $t->is_active && ! $t->is_paused,
            ])
            ->values()
            ->all();

        // ── Status / trigger breakdowns (as arrays) ──────────────────
        $executionsByStatus = [];
        foreach ($byStatusFront as $status => $count) {
            $executionsByStatus[] = ['status' => $status, 'count' => $count];
        }

        $triggerTypeStats = [];
        foreach ($byTriggerFront as $trigger => $count) {
            $triggerTypeStats[] = ['trigger_type' => $trigger, 'count' => $count];
        }

        return $this->successResponse('Dashboard data retrieved.', [
            'summary' => $summary,
            'recent_executions' => $recentExecutions,
            'top_workflows' => $topWorkflows,
            'recent_failures' => $recentFailures,
            'executions_by_day' => $executionsByDay,
            'executions_by_hour' => $executionsByHour,
            'upcoming_schedules' => $upcomingSchedules,
            'executions_by_status' => $executionsByStatus,
            'trigger_type_stats' => $triggerTypeStats,
        ]);
    }

    /**
     * Lightweight header/badge counters — frontend `IQuickStats` contract.
     */
    public function quickStats(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::ExecutionView)) {
            return $denied;
        }

        $execs = $workspace->executions();
        $soon = Carbon::now()->addDays(7);

        return $this->successResponse('Quick stats retrieved.', [
            'workflows' => [
                'total' => $workspace->workflows()->count(),
                'active' => $workspace->workflows()->where('is_active', true)->count(),
            ],
            'executions' => [
                'running' => (clone $execs)
                    ->whereIn('status', [ExecutionStatus::Running->value, ExecutionStatus::Pending->value])
                    ->count(),
                'queued' => (clone $execs)->where('status', ExecutionStatus::Waiting->value)->count(),
                'today' => (clone $execs)->where('created_at', '>=', Carbon::now()->startOfDay())->count(),
            ],
            'credentials' => [
                'total' => $workspace->credentials()->count(),
                'expiring_soon' => $workspace->credentials()
                    ->whereNotNull('expires_at')
                    ->whereBetween('expires_at', [Carbon::now(), $soon])
                    ->count(),
            ],
            'schedules' => [
                'total' => $workspace->triggers()->whereNotNull('schedule_expression')->count(),
                'active' => $workspace->triggers()
                    ->whereNotNull('schedule_expression')
                    ->where('is_active', true)
                    ->where('is_paused', false)
                    ->count(),
            ],
        ]);
    }

    /**
     * Map a `period` shorthand (7d/30d/90d) to a day count.
     */
    private function periodToDays(string $period): int
    {
        return match ($period) {
            '30d' => 30,
            '90d' => 90,
            default => 7,
        };
    }

    /**
     * Map a backend execution status onto the frontend status vocabulary.
     */
    private function mapStatusToFront(string $status): string
    {
        return match ($status) {
            ExecutionStatus::Pending->value => 'queued',
            ExecutionStatus::Waiting->value => 'paused',
            default => $status, // running, completed, failed, cancelled align 1:1
        };
    }

    /**
     * Map a backend execution mode onto the frontend trigger-type vocabulary.
     */
    private function mapTriggerToFront(string $mode): string
    {
        return match ($mode) {
            ExecutionMode::Scheduled->value => 'schedule',
            ExecutionMode::Webhook->value, ExecutionMode::Polling->value => 'webhook',
            default => 'manual',
        };
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
