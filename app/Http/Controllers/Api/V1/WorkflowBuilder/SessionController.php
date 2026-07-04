<?php

namespace App\Http\Controllers\Api\V1\WorkflowBuilder;

use App\Enums\BuilderSessionStatus;
use App\Enums\Permission;
use App\Exceptions\DraftConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\WorkflowBuilder\StoreSessionRequest;
use App\Http\Requests\Api\V1\WorkflowBuilder\UpdateSessionRequest;
use App\Http\Resources\V1\WorkflowBuilder\WorkflowBuilderSessionResource;
use App\Http\Resources\V1\WorkflowResource;
use App\Models\WorkflowBuilderSession;
use App\Models\Workspace;
use App\Services\WorkflowBuilder\MessageService;
use App\Services\WorkflowBuilder\SaveService;
use App\Services\WorkflowBuilder\SessionService;
use App\Services\WorkflowBuilder\ValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function __construct(
        private readonly SessionService $sessionService,
        private readonly MessageService $messageService,
        private readonly ValidationService $validationService,
        private readonly SaveService $saveService,
    ) {}

    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowCreate)) {
            return $denied;
        }

        $status = BuilderSessionStatus::tryFrom($request->query('status', 'active'))
            ?? BuilderSessionStatus::Active;

        $sessions = WorkflowBuilderSession::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $request->user()->id)
            ->where('status', $status)
            ->withCount(['messages', 'draftVersions'])
            ->latest('last_activity_at')
            ->paginate((int) $request->query('per_page', 20));

        return $this->paginatedResponse('Sessions retrieved.', WorkflowBuilderSessionResource::collection($sessions));
    }

    public function store(StoreSessionRequest $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowCreate)) {
            return $denied;
        }

        $data = $request->validated();
        $session = $this->sessionService->create($workspace, $request->user(), $data);

        if (! empty($data['prompt'])) {
            $pending = $this->messageService->send($session, $request->user(), $data['prompt']);

            return $this->successResponse('Session created. Processing initial prompt.', [
                'session_id' => $session->id,
                'message_id' => $pending->id,
            ], 202);
        }

        return $this->successResponse('Session created.', new WorkflowBuilderSessionResource($session), 201);
    }

    public function show(Request $request, Workspace $workspace, WorkflowBuilderSession $builderSession): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowCreate)) {
            return $denied;
        }

        if (! $builderSession->isOwnedBy($request->user()) || $builderSession->workspace_id !== $workspace->id) {
            return $this->errorResponse('Session not found.', 404);
        }

        $builderSession->load('messages');
        $builderSession->loadCount(['messages', 'draftVersions']);

        return $this->successResponse('Session retrieved.', new WorkflowBuilderSessionResource($builderSession));
    }

    public function update(UpdateSessionRequest $request, Workspace $workspace, WorkflowBuilderSession $builderSession): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowCreate)) {
            return $denied;
        }

        if (! $builderSession->isOwnedBy($request->user()) || $builderSession->workspace_id !== $workspace->id) {
            return $this->errorResponse('Session not found.', 404);
        }

        if (! $builderSession->isActive()) {
            return $this->errorResponse('Only active sessions can be renamed.', 422);
        }

        $session = $this->sessionService->rename($builderSession, $request->validated('title'));

        return $this->successResponse('Session renamed.', new WorkflowBuilderSessionResource($session));
    }

    public function destroy(Request $request, Workspace $workspace, WorkflowBuilderSession $builderSession): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowCreate)) {
            return $denied;
        }

        if (! $builderSession->isOwnedBy($request->user()) || $builderSession->workspace_id !== $workspace->id) {
            return $this->errorResponse('Session not found.', 404);
        }

        $this->sessionService->archive($builderSession);

        return $this->successResponse('Session discarded.');
    }

    public function validate(Request $request, Workspace $workspace, WorkflowBuilderSession $builderSession): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowCreate)) {
            return $denied;
        }

        if (! $builderSession->isOwnedBy($request->user()) || $builderSession->workspace_id !== $workspace->id) {
            return $this->errorResponse('Session not found.', 404);
        }

        $errors = $this->validationService->validate($builderSession);

        return $this->successResponse('Validation complete.', [
            'valid' => empty($errors),
            'errors' => $errors,
        ]);
    }

    public function save(Request $request, Workspace $workspace, WorkflowBuilderSession $builderSession): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowCreate)) {
            return $denied;
        }

        if (! $builderSession->isOwnedBy($request->user()) || $builderSession->workspace_id !== $workspace->id) {
            return $this->errorResponse('Session not found.', 404);
        }

        try {
            $result = $this->saveService->save($builderSession, $request->user());
        } catch (DraftConflictException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        }

        if (! empty($result['errors'])) {
            return $this->errorResponse('Workflow has validation errors.', 422, $result['errors']);
        }

        return $this->successResponse('Workflow saved.', new WorkflowResource($result['workflow']), 201);
    }
}
