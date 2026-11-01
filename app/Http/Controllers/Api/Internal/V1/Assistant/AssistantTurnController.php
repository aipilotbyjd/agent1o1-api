<?php

namespace App\Http\Controllers\Api\Internal\V1\Assistant;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Api\Internal\V1\Assistant\Concerns\ResolvesOwnAssistant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Assistant\DecideAssistantActionsRequest;
use App\Http\Resources\Api\Internal\V1\Assistant\AssistantTurnResource;
use App\Http\Responses\ApiResponse;
use App\Models\Assistant\AssistantSession;
use App\Models\Assistant\AssistantTurn;
use App\Models\Workspaces\Workspace;
use App\Services\Assistant\Runtime\AssistantLoop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantTurnController extends Controller
{
    use ResolvesOwnAssistant;

    public function __construct(private AssistantLoop $loop) {}

    public function show(Request $request, Workspace $workspace, AssistantSession $session, AssistantTurn $turn): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);
        $this->ensureOwnTurn($request, $workspace, $session, $turn);

        return ApiResponse::success(['turn' => AssistantTurnResource::make($turn->load('actions'))]);
    }

    public function cancel(Request $request, Workspace $workspace, AssistantSession $session, AssistantTurn $turn): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);
        $this->ensureOwnTurn($request, $workspace, $session, $turn);

        $turn = $this->loop->cancel($turn);

        return ApiResponse::success(['turn' => AssistantTurnResource::make($turn->load('actions'))], 'Stopping.');
    }

    public function decide(DecideAssistantActionsRequest $request, Workspace $workspace, AssistantSession $session, AssistantTurn $turn): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);
        $this->ensureOwnTurn($request, $workspace, $session, $turn);

        $turn = $this->loop->decide($turn, $request->decisions());

        return ApiResponse::success(['turn' => AssistantTurnResource::make($turn->load('actions'))], 'Decisions recorded.');
    }
}
