<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::MemberView)) {
            return $denied;
        }

        $logs = ActivityLog::query()
            ->with('user')
            ->where('workspace_id', $workspace->id)
            ->when($request->query('action'), fn ($q, $action) => $q->where('action', $action))
            ->when($request->query('user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->latest()
            ->paginate((int) $request->query('per_page', 30));

        return $this->paginatedResponse('Activity log retrieved.', ActivityLogResource::collection($logs));
    }
}
