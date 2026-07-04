<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ExecutionLogResource;
use App\Models\Execution;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExecutionLogController extends Controller
{
    public function index(Request $request, Workspace $workspace, Execution $execution): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::ExecutionView)) {
            return $denied;
        }

        $logs = $execution->logs()
            ->when($request->query('level'), fn ($q, $level) => $q->where('level', $level))
            ->orderBy('logged_at')
            ->paginate((int) $request->query('per_page', 100));

        return $this->paginatedResponse('Execution logs retrieved.', ExecutionLogResource::collection($logs));
    }
}
