<?php

namespace App\Http\Controllers\Api\Internal\V1\Auth;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Auth\StoreApiKeyRequest;
use App\Http\Resources\Api\Internal\V1\Auth\ApiKeyResource;
use App\Http\Responses\ApiResponse;
use App\Models\Auth\ApiKey;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\Request;

class ApiKeyController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::ApiKeyView);

        return ApiResponse::success(['api_keys' => ApiKeyResource::collection($workspace->apiKeys()->latest()->get())]);
    }

    public function store(StoreApiKeyRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::ApiKeyManage);

        $plainTextKey = ApiKey::generatePlainTextKey();

        $apiKey = $workspace->apiKeys()->create([
            'name' => $request->validated('name'),
            'hashed_key' => ApiKey::hash($plainTextKey),
            'abilities' => $request->validated('abilities'),
            'expires_at' => $request->validated('expires_at'),
        ]);

        return ApiResponse::created([
            'api_key' => ApiKeyResource::make($apiKey),
            'plain_text_key' => $plainTextKey,
        ], 'API key created successfully.');
    }

    public function destroy(Request $request, Workspace $workspace, ApiKey $apiKey)
    {
        $this->requirePermission(Permission::ApiKeyManage);
        $this->ensureBelongsToWorkspace($workspace, $apiKey);

        $apiKey->delete();

        return ApiResponse::noContent();
    }
}
