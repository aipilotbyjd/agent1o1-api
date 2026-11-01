<?php

namespace App\Http\Controllers\Api\Internal\V1\Assistant;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Api\Internal\V1\Assistant\Concerns\ResolvesOwnAssistant;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Assistant\AssistantMemory;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * What the assistant has remembered about its owner: viewable, and
 * removable, by the owner.
 */
class AssistantMemoryController extends Controller
{
    use ResolvesOwnAssistant;

    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);

        $memories = $this->ownAssistant($request, $workspace)->memories()->orderBy('key')->get(['id', 'key', 'value', 'updated_at']);

        return ApiResponse::success(['memories' => $memories]);
    }

    public function destroy(Request $request, Workspace $workspace, AssistantMemory $memory): Response
    {
        $this->requirePermission(Permission::AssistantUse);

        abort_if($memory->assistant_id !== $this->ownAssistant($request, $workspace)->id, 404);

        $memory->delete();

        return ApiResponse::noContent();
    }
}
