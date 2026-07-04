<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Credential\InitiateOAuthRequest;
use App\Http\Resources\V1\CredentialResource;
use App\Models\Workspace;
use App\Services\OAuthCredentialFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class OAuthCredentialController extends Controller
{
    public function __construct(private readonly OAuthCredentialFlowService $flow) {}

    public function initiate(InitiateOAuthRequest $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::CredentialManage)) {
            return $denied;
        }

        try {
            $result = $this->flow->initiate(
                $workspace,
                $request->user(),
                $request->validated('credential_type_key'),
                $request->validated('name'),
            );
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse('OAuth flow initiated.', $result);
    }

    /**
     * Public callback hit by the OAuth provider after user consent.
     */
    public function callback(Request $request): JsonResponse
    {
        $request->validate([
            'state' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        try {
            $credential = $this->flow->handleCallback($request->string('state'), $request->string('code'));
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse('Credential connected.', new CredentialResource($credential), 201);
    }
}
