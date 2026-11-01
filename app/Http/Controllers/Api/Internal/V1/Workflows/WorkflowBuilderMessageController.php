<?php

namespace App\Http\Controllers\Api\Internal\V1\Workflows;

use App\Actions\Workflows\Builder\SendWorkflowBuilderMessageAction;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Workflows\StoreWorkflowBuilderMessageRequest;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowBuilderMessageResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Builder\WorkflowBuilderMessage;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Models\Workspaces\Workspace;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sending returns 202 with the pending assistant message: the reply is
 * written by `ProcessWorkflowBuilderMessageJob` and streamed on the
 * session's channel (`WorkflowBuilderActivity`). A client without a socket
 * polls `show()` until `processing_status` is `completed` or `failed`.
 */
class WorkflowBuilderMessageController extends Controller
{
    public function __construct(private readonly SendWorkflowBuilderMessageAction $sendMessage) {}

    public function index(Workspace $workspace, WorkflowBuilderSession $session)
    {
        $this->requirePermission(Permission::WorkflowBuilderUse);
        $this->ensureBelongsToWorkspace($workspace, $session);

        return ApiResponse::success([
            'messages' => WorkflowBuilderMessageResource::collection($session->messages()->with(['attachments', 'modelCatalog'])->oldest()->oldest('id')->get()),
        ]);
    }

    public function store(StoreWorkflowBuilderMessageRequest $request, Workspace $workspace, WorkflowBuilderSession $session)
    {
        $this->requirePermission(Permission::WorkflowBuilderUse);
        $this->ensureBelongsToWorkspace($workspace, $session);

        $reply = $this->sendMessage->execute($session, $request->validated('message'), $request->user(), $request->attachmentFiles(), $request->mode(), $request->context(), $request->validated('model_catalog_id'));

        return ApiResponse::success(
            ['message' => WorkflowBuilderMessageResource::make($reply)],
            'Message sent.',
            Response::HTTP_ACCEPTED,
        );
    }

    public function show(Workspace $workspace, WorkflowBuilderSession $session, WorkflowBuilderMessage $message)
    {
        $this->requirePermission(Permission::WorkflowBuilderUse);
        $this->ensureBelongsToWorkspace($workspace, $session);
        abort_if($message->session_id !== $session->id, 404);

        return ApiResponse::success(['message' => WorkflowBuilderMessageResource::make($message->load(['attachments', 'modelCatalog']))]);
    }
}
