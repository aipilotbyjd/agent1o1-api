<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Node\SandboxRequest;
use App\Models\Workspace;
use App\Services\NodeSandboxService;
use Illuminate\Http\JsonResponse;

class NodeSandboxController extends Controller
{
    public function __construct(private readonly NodeSandboxService $sandboxService) {}

    public function execute(SandboxRequest $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowUpdate)) {
            return $denied;
        }

        $result = $this->sandboxService->run(
            $workspace,
            $request->validated('type'),
            $request->validated('config') ?? [],
            $request->validated('input') ?? [],
            $request->validated('credentials'),
        );

        return $this->successResponse('Node executed in sandbox.', $result);
    }
}
