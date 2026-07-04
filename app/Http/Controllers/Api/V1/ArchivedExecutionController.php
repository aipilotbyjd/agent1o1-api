<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\ArchivedExecutionLog;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArchivedExecutionController extends Controller
{
    /**
     * Query execution logs that have aged out into cold storage.
     */
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::ExecutionView)) {
            return $denied;
        }

        $logs = ArchivedExecutionLog::query()
            ->where('workspace_id', $workspace->id)
            ->when($request->query('execution_id'), fn ($q, $id) => $q->where('execution_id', $id))
            ->when($request->query('level'), fn ($q, $level) => $q->where('level', $level))
            ->when($request->query('from'), fn ($q, $from) => $q->where('logged_at', '>=', $from))
            ->when($request->query('to'), fn ($q, $to) => $q->where('logged_at', '<=', $to))
            ->orderByDesc('logged_at')
            ->paginate((int) $request->query('per_page', 100));

        return $this->successResponse('Archived logs retrieved.', [
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
