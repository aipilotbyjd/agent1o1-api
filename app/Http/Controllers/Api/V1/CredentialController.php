<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Credential\StoreCredentialRequest;
use App\Http\Requests\Api\V1\Credential\UpdateCredentialRequest;
use App\Http\Resources\V1\CredentialResource;
use App\Models\Credential;
use App\Models\Workspace;
use App\Services\CredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CredentialController extends Controller
{
    public function __construct(private readonly CredentialService $credentialService) {}

    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::CredentialView)) {
            return $forbidden;
        }

        $credentials = $workspace->credentials()
            ->with('creator')
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->latest()
            ->paginate((int) $request->query('per_page', 15));

        return $this->paginatedResponse(
            'Credentials retrieved.',
            CredentialResource::collection($credentials),
        );
    }

    public function store(StoreCredentialRequest $request, Workspace $workspace): JsonResponse
    {
        if ($forbidden = $this->requirePermission(Permission::CredentialManage)) {
            return $forbidden;
        }

        $credential = $this->credentialService->create($workspace, $request->user(), $request->validated());

        return $this->successResponse('Credential created.', new CredentialResource($credential), 201);
    }

    public function show(Request $request, Workspace $workspace, Credential $credential): JsonResponse
    {
        abort_if($credential->workspace_id !== $workspace->id, 404);

        if ($forbidden = $this->requirePermission(Permission::CredentialView)) {
            return $forbidden;
        }

        return $this->successResponse('Credential retrieved.', new CredentialResource($credential->load('creator')));
    }

    public function update(UpdateCredentialRequest $request, Workspace $workspace, Credential $credential): JsonResponse
    {
        abort_if($credential->workspace_id !== $workspace->id, 404);

        if ($forbidden = $this->requirePermission(Permission::CredentialManage)) {
            return $forbidden;
        }

        $credential = $this->credentialService->update($credential, $request->validated());

        return $this->successResponse('Credential updated.', new CredentialResource($credential));
    }

    public function destroy(Request $request, Workspace $workspace, Credential $credential): JsonResponse
    {
        abort_if($credential->workspace_id !== $workspace->id, 404);

        if ($forbidden = $this->requirePermission(Permission::CredentialManage)) {
            return $forbidden;
        }

        $credential->delete();

        return $this->successResponse('Credential deleted.');
    }

    public function test(Request $request, Workspace $workspace, Credential $credential): JsonResponse
    {
        abort_if($credential->workspace_id !== $workspace->id, 404);

        if ($forbidden = $this->requirePermission(Permission::CredentialView)) {
            return $forbidden;
        }

        $result = $this->credentialService->test($credential);

        return $result['ok']
            ? $this->successResponse($result['message'])
            : $this->errorResponse($result['message'], 422);
    }
}
