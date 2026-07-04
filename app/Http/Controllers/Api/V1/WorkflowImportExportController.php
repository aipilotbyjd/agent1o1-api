<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Workflow\ImportWorkflowRequest;
use App\Http\Resources\V1\WorkflowResource;
use App\Models\Workflow;
use App\Models\Workspace;
use App\Services\WorkflowImportExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowImportExportController extends Controller
{
    public function __construct(private readonly WorkflowImportExportService $importExport) {}

    public function export(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowView)) {
            return $denied;
        }

        return $this->successResponse('Workflow exported.', $this->importExport->export($workflow->load('currentVersion')));
    }

    public function import(ImportWorkflowRequest $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowCreate)) {
            return $denied;
        }

        $workflow = $this->importExport->import($workspace, $request->user(), $request->validated());

        return $this->successResponse('Workflow imported.', new WorkflowResource($workflow), 201);
    }
}
