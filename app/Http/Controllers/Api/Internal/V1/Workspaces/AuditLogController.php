<?php

namespace App\Http\Controllers\Api\Internal\V1\Workspaces;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Workspaces\IndexAuditLogsRequest;
use App\Http\Resources\Api\Internal\V1\Workspaces\AuditLogResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\Workspace;

class AuditLogController extends Controller
{
    public function index(IndexAuditLogsRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::AuditLogView);

        $logs = $workspace->auditLogs()
            ->when($request->validated('action'), fn ($query, $action) => $query->where('action', $action))
            ->when($request->validated('actor_id'), fn ($query, $actorId) => $query->where('actor_id', $actorId))
            ->when($request->validated('from'), fn ($query, $from) => $query->where('created_at', '>=', $from))
            ->when($request->validated('to'), fn ($query, $to) => $query->where('created_at', '<=', $to))
            ->latest('created_at')
            ->latest('id')
            ->paginate((int) $request->validated('per_page', 25));

        return ApiResponse::paginated(AuditLogResource::collection($logs));
    }
}
