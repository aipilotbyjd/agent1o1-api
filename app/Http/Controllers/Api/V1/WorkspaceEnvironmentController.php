<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Models\WorkspaceEnvironment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkspaceEnvironmentController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowView)) {
            return $denied;
        }

        return $this->successResponse(
            'Environments retrieved.',
            $workspace->environments()->get()->toArray(),
        );
    }

    public function store(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkspaceUpdate)) {
            return $denied;
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'variables' => ['nullable', 'array'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $environment = $workspace->environments()->create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'variables' => $data['variables'] ?? [],
            'is_default' => $data['is_default'] ?? false,
        ]);

        return $this->successResponse('Environment created.', $environment->toArray(), 201);
    }

    public function update(Request $request, Workspace $workspace, WorkspaceEnvironment $environment): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkspaceUpdate)) {
            return $denied;
        }

        $environment->update($request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'variables' => ['sometimes', 'array'],
            'is_default' => ['sometimes', 'boolean'],
        ]));

        return $this->successResponse('Environment updated.', $environment->toArray());
    }

    public function destroy(Request $request, Workspace $workspace, WorkspaceEnvironment $environment): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkspaceUpdate)) {
            return $denied;
        }

        $environment->delete();

        return $this->successResponse('Environment deleted.');
    }
}
