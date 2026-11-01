<?php

namespace App\Http\Controllers\Api\Internal\V1\Workflows;

use App\Actions\Workflows\Builder\CreateWorkflowBuilderSessionAction;
use App\Actions\Workflows\Builder\PromoteWorkflowBuilderSessionAction;
use App\Actions\Workflows\Builder\SendWorkflowBuilderMessageAction;
use App\Enums\Workflows\BuilderSessionStatus;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Workflows\PromoteWorkflowBuilderSessionRequest;
use App\Http\Requests\Api\Internal\V1\Workflows\StoreWorkflowBuilderSessionRequest;
use App\Http\Requests\Api\Internal\V1\Workflows\SyncWorkflowBuilderDraftRequest;
use App\Http\Requests\Api\Internal\V1\Workflows\UpdateWorkflowBuilderSessionRequest;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowBuilderMessageResource;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowBuilderSessionResource;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * The chat-based workflow builder: sessions here own a `draft_graph` edited
 * through `WorkflowBuilderAgent`'s tools (see `WorkflowBuilderMessageController`)
 * and, via `syncDraft()`, by hand on the canvas. Separate from
 * `WorkflowBuilderController`, which is the direct editor-autosave endpoint
 * on an already-created `Workflow`.
 */
class WorkflowBuilderSessionController extends Controller
{
    public function __construct(
        private readonly CreateWorkflowBuilderSessionAction $createSession,
        private readonly PromoteWorkflowBuilderSessionAction $promoteSession,
        private readonly SendWorkflowBuilderMessageAction $sendMessage,
    ) {}

    public function index(Request $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::WorkflowBuilderUse);

        $request->validate([
            'status' => ['nullable', Rule::enum(BuilderSessionStatus::class)],
            'mine' => ['nullable', 'boolean'],
        ]);

        $sessions = $workspace->builderSessions()
            // A list doesn't need every session's whole graph.
            ->select(WorkflowBuilderSessionResource::LIST_COLUMNS)
            ->when($request->boolean('mine'), fn ($query) => $query->where('user_id', $request->user()->id))
            ->when(
                $request->query('status'),
                fn ($query, string $status) => $query->where('status', $status),
                // Archived sessions are history, only listed when asked for.
                fn ($query) => $query->where('status', '!=', BuilderSessionStatus::Archived),
            )
            ->withCount('messages')
            ->latest('last_activity_at')
            ->latest()
            ->get();

        return ApiResponse::success([
            'sessions' => WorkflowBuilderSessionResource::collection($sessions),
        ]);
    }

    /**
     * With a `prompt`, the session's first message is sent straight away:
     * "describe the workflow you want" in one request. The reply is
     * generated in the background; the pending assistant message is
     * returned so the client can follow it.
     */
    public function store(StoreWorkflowBuilderSessionRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::WorkflowBuilderUse);

        $workflow = null;

        if ($request->validated('workflow_id')) {
            $workflow = Workflow::findOrFail($request->validated('workflow_id'));
            $this->ensureBelongsToWorkspace($workspace, $workflow);
        }

        $session = $this->createSession->execute($workspace, $request->user(), $request->validated('title'), $workflow);

        $reply = $request->validated('prompt')
            ? $this->sendMessage->execute($session, $request->validated('prompt'), $request->user(), mode: $request->mode())
            : null;

        return ApiResponse::created([
            'session' => WorkflowBuilderSessionResource::make($session->fresh()),
            'message' => $reply ? WorkflowBuilderMessageResource::make($reply) : null,
        ], 'Session created successfully.');
    }

    public function show(Workspace $workspace, WorkflowBuilderSession $session)
    {
        $this->requirePermission(Permission::WorkflowBuilderUse);
        $this->ensureBelongsToWorkspace($workspace, $session);

        return ApiResponse::success([
            'session' => WorkflowBuilderSessionResource::make($session->load(['messages' => fn ($query) => $query->oldest()->oldest('id')->with(['attachments', 'modelCatalog'])])),
        ]);
    }

    /**
     * Rename, archive, or unarchive.
     */
    public function update(UpdateWorkflowBuilderSessionRequest $request, Workspace $workspace, WorkflowBuilderSession $session)
    {
        $this->requirePermission(Permission::WorkflowBuilderUse);
        $this->ensureBelongsToWorkspace($workspace, $session);

        $attributes = $request->validated();

        // Unarchiving puts a session back where it was: a session that was
        // already promoted keeps pointing at its workflow.
        if (($attributes['status'] ?? null) === BuilderSessionStatus::Active->value && $session->workflow_id !== null) {
            $attributes['status'] = BuilderSessionStatus::Promoted;
        }

        $session->update($attributes);

        return ApiResponse::success([
            'session' => WorkflowBuilderSessionResource::make($session),
        ], 'Session updated.');
    }

    /**
     * Replace the draft with the canvas's copy after the user edited it by
     * hand, so the assistant's next turn works from what the user sees. The
     * client sends the `draft_lock_version` it last loaded; a 409 means the
     * assistant changed the draft since, and the client must reload first.
     */
    public function syncDraft(SyncWorkflowBuilderDraftRequest $request, Workspace $workspace, WorkflowBuilderSession $session)
    {
        $this->requirePermission(Permission::WorkflowBuilderUse);
        $this->ensureBelongsToWorkspace($workspace, $session);
        $session->assertEditable();

        try {
            $session->replaceDraft(
                ['nodes' => $request->validated('nodes'), 'edges' => $request->validated('edges')],
                (int) $request->validated('draft_lock_version'),
                $request->user(),
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::validationError(['graph' => [$exception->getMessage()]], $exception->getMessage());
        }

        return ApiResponse::success([
            'session' => WorkflowBuilderSessionResource::make($session),
        ], 'Draft saved.');
    }

    public function destroy(Workspace $workspace, WorkflowBuilderSession $session)
    {
        $this->requirePermission(Permission::WorkflowBuilderUse);
        $this->ensureBelongsToWorkspace($workspace, $session);

        $session->delete();

        return ApiResponse::noContent();
    }

    /**
     * Save the draft graph as a real, workspace-visible `Workflow`'s draft:
     * a new workflow unless the session was already started from (or
     * already promoted to) one. A 409 means that workflow was edited
     * elsewhere since; `overwrite: true` replaces those edits.
     */
    public function promote(PromoteWorkflowBuilderSessionRequest $request, Workspace $workspace, WorkflowBuilderSession $session)
    {
        $this->requirePermission(Permission::WorkflowBuilderUse);
        $this->ensureBelongsToWorkspace($workspace, $session);

        $workflow = $this->promoteSession->execute(
            $session,
            $request->user(),
            $request->validated('name'),
            $request->boolean('overwrite'),
        );

        return ApiResponse::success([
            'workflow' => WorkflowResource::make($workflow->fresh(['nodes', 'edges'])),
        ], 'Draft saved to the workflow.');
    }
}
