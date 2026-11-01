<?php

namespace App\Http\Controllers\Api\Internal\V1\Assistant;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Assistant\UpdateAssistantRequest;
use App\Http\Resources\Api\Internal\V1\Assistant\AssistantResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\Workspace;
use App\Services\Assistant\AssistantProvisioner;
use App\Services\Assistant\Branding\BrandRepository;
use App\Services\Assistant\Runtime\VoiceInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in member's own assistant. There is no id in the URL: each
 * member only ever reaches theirs, so nobody can address someone else's.
 */
class AssistantController extends Controller
{
    public function __construct(
        private AssistantProvisioner $provisioner,
        private BrandRepository $brands,
        private VoiceInput $voice,
    ) {}

    public function show(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        $assistant = $this->provisioner->forMember($workspace, $request->user());

        return ApiResponse::success([
            'assistant' => AssistantResource::make($assistant),
            'brand' => $this->brands->current($workspace)->toArray(),
            'features' => [
                'voice' => $this->voice->isAvailable(),
            ],
        ]);
    }

    public function update(UpdateAssistantRequest $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        $assistant = $this->provisioner->forMember($workspace, $request->user());
        $assistant->update($request->validated());

        return ApiResponse::success([
            'assistant' => AssistantResource::make($assistant),
        ], 'Assistant updated successfully.');
    }
}
