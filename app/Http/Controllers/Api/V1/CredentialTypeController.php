<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CredentialTypeResource;
use App\Models\CredentialType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CredentialTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $types = CredentialType::query()
            ->where('is_active', true)
            ->when($request->query('auth_type'), fn ($q, $auth) => $q->where('auth_type', $auth))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->successResponse('Credential types retrieved.', CredentialTypeResource::collection($types));
    }

    public function show(Request $request, CredentialType $credentialType): JsonResponse
    {
        return $this->successResponse('Credential type retrieved.', new CredentialTypeResource($credentialType));
    }
}
