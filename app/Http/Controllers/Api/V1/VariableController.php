<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Variable\StoreVariableRequest;
use App\Http\Requests\Api\V1\Variable\UpdateVariableRequest;
use App\Http\Resources\V1\VariableResource;
use App\Models\Variable;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VariableController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::VariableView)) {
            return $forbidden;
        }

        $variables = $workspace->variables()
            ->with('creator')
            ->orderBy('key')
            ->paginate((int) $request->query('per_page', 50));

        return $this->paginatedResponse(
            'Variables retrieved.',
            VariableResource::collection($variables),
        );
    }

    public function store(StoreVariableRequest $request, Workspace $workspace): JsonResponse
    {
        $data = $request->validated();

        if ($workspace->variables()->where('key', $data['key'])->exists()) {
            return $this->errorResponse('A variable with this key already exists.', 422);
        }

        $isSecret = (bool) ($data['is_secret'] ?? false);

        $variable = Variable::create([
            'workspace_id' => $workspace->id,
            'created_by' => $request->user()->id,
            'key' => $data['key'],
            'value' => $isSecret ? encrypt($data['value'] ?? '') : ($data['value'] ?? null),
            'is_secret' => $isSecret,
        ]);

        return $this->successResponse('Variable created.', new VariableResource($variable), 201);
    }

    public function show(Request $request, Workspace $workspace, Variable $variable): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::VariableView)) {
            return $forbidden;
        }

        return $this->successResponse('Variable retrieved.', new VariableResource($variable));
    }

    public function update(UpdateVariableRequest $request, Workspace $workspace, Variable $variable): JsonResponse
    {
        $data = $request->validated();
        $isSecret = (bool) ($data['is_secret'] ?? $variable->is_secret);

        $variable->update([
            'value' => $isSecret ? encrypt($data['value'] ?? '') : ($data['value'] ?? null),
            'is_secret' => $isSecret,
        ]);

        return $this->successResponse('Variable updated.', new VariableResource($variable->fresh()));
    }

    public function destroy(Request $request, Workspace $workspace, Variable $variable): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::VariableManage)) {
            return $forbidden;
        }

        $variable->delete();

        return $this->successResponse('Variable deleted.');
    }
}
