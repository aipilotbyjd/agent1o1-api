<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ConnectorMetricResource;
use App\Models\ConnectorMetric;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConnectorMetricController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::ExecutionView)) {
            return $denied;
        }

        $metrics = ConnectorMetric::query()
            ->where('workspace_id', $workspace->id)
            ->when($request->query('connector'), fn ($q, $c) => $q->where('connector', $c))
            ->when($request->query('from'), fn ($q, $from) => $q->where('date', '>=', $from))
            ->when($request->query('to'), fn ($q, $to) => $q->where('date', '<=', $to))
            ->orderByDesc('date')
            ->get();

        return $this->successResponse('Connector metrics retrieved.', ConnectorMetricResource::collection($metrics));
    }

    public function summary(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::ExecutionView)) {
            return $denied;
        }

        $summary = ConnectorMetric::query()
            ->where('workspace_id', $workspace->id)
            ->select('connector')
            ->selectRaw('SUM(total_calls) as total_calls')
            ->selectRaw('SUM(success_calls) as success_calls')
            ->selectRaw('SUM(failed_calls) as failed_calls')
            ->selectRaw('SUM(total_duration_ms) as total_duration_ms')
            ->groupBy('connector')
            ->orderByDesc(DB::raw('SUM(total_calls)'))
            ->get();

        return $this->successResponse('Connector summary retrieved.', $summary->toArray());
    }
}
