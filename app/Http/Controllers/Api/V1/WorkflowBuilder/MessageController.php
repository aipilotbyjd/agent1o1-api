<?php

namespace App\Http\Controllers\Api\V1\WorkflowBuilder;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\WorkflowBuilder\StoreMessageRequest;
use App\Http\Resources\V1\WorkflowBuilder\WorkflowBuilderMessageResource;
use App\Models\WorkflowBuilderSession;
use App\Models\Workspace;
use App\Services\WorkflowBuilder\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function __construct(private readonly MessageService $messageService) {}

    public function index(Request $request, Workspace $workspace, WorkflowBuilderSession $builderSession): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowCreate)) {
            return $denied;
        }

        if (! $builderSession->isOwnedBy($request->user()) || $builderSession->workspace_id !== $workspace->id) {
            return $this->errorResponse('Session not found.', 404);
        }

        $messages = $builderSession->messages()
            ->oldest()
            ->paginate((int) $request->query('per_page', 50));

        return $this->paginatedResponse('Messages retrieved.', WorkflowBuilderMessageResource::collection($messages));
    }

    public function store(StoreMessageRequest $request, Workspace $workspace, WorkflowBuilderSession $builderSession): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowCreate)) {
            return $denied;
        }

        if (! $builderSession->isOwnedBy($request->user()) || $builderSession->workspace_id !== $workspace->id) {
            return $this->errorResponse('Session not found.', 404);
        }

        if (! $builderSession->isActive()) {
            return $this->errorResponse('This session is no longer active.', 422);
        }

        $pending = $this->messageService->send($builderSession, $request->user(), $request->validated('message'));

        return $this->successResponse('Message queued.', ['message_id' => $pending->id], 202);
    }
}
