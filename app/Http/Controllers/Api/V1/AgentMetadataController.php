<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Services\AgentMetadataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only discovery endpoints backing the agent builder: available AI
 * providers/models, the tool catalog, categories, and trigger types.
 */
class AgentMetadataController extends Controller
{
    public function __construct(private readonly AgentMetadataService $metadata) {}

    public function providers(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            return $denied;
        }

        return $this->successResponse('Providers retrieved.', ['providers' => $this->metadata->providers()]);
    }

    public function models(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            return $denied;
        }

        return $this->successResponse('Models retrieved.', [
            'providers' => $this->metadata->models($request->query('provider')),
        ]);
    }

    public function tools(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            return $denied;
        }

        return $this->successResponse('Tools retrieved.', ['tools' => $this->metadata->tools($workspace)]);
    }

    public function categories(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            return $denied;
        }

        return $this->successResponse('Categories retrieved.', ['categories' => $this->metadata->categories($workspace)]);
    }

    public function triggerTypes(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            return $denied;
        }

        return $this->successResponse('Trigger types retrieved.', ['trigger_types' => $this->metadata->triggerTypes()]);
    }
}
