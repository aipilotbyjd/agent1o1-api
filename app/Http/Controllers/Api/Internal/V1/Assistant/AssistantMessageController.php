<?php

namespace App\Http\Controllers\Api\Internal\V1\Assistant;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Api\Internal\V1\Assistant\Concerns\ResolvesOwnAssistant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Assistant\SendAssistantMessageRequest;
use App\Http\Resources\Api\Internal\V1\Assistant\AssistantTurnResource;
use App\Http\Responses\ApiResponse;
use App\Models\Assistant\AssistantSession;
use App\Models\Assistant\AssistantTurn;
use App\Models\Workspaces\Workspace;
use App\Services\Assistant\Runtime\AssistantLoop;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sending a message never waits for the reply: the turn runs on the queue
 * and streams over the session's channel. While a turn is running, the
 * message is queued instead and answered next.
 */
class AssistantMessageController extends Controller
{
    use ResolvesOwnAssistant;

    public function store(SendAssistantMessageRequest $request, Workspace $workspace, AssistantSession $session, AssistantLoop $loop): JsonResponse
    {
        $this->requirePermission(Permission::AssistantUse);
        $this->ensureOwnSession($request, $workspace, $session);

        $result = $loop->send($session, $request->validated('content'));

        if ($result instanceof AssistantTurn) {
            return ApiResponse::success([
                'turn' => AssistantTurnResource::make($result),
                'queued' => false,
            ], 'Message sent.', Response::HTTP_ACCEPTED);
        }

        return ApiResponse::success([
            'turn' => null,
            'queued' => true,
        ], 'Message queued until the current reply finishes.', Response::HTTP_ACCEPTED);
    }
}
