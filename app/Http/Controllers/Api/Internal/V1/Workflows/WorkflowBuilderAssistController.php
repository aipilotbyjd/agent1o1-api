<?php

namespace App\Http\Controllers\Api\Internal\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Workflows\ConfigureWorkflowBuilderNodeRequest;
use App\Http\Requests\Api\Internal\V1\Workflows\SuggestWorkflowBuilderNodesRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\WorkflowBuilderAssistant;
use InvalidArgumentException;

/**
 * One-shot helpers over a builder session's draft: see
 * `WorkflowBuilderAssistant`. None of them change the draft; each returns a
 * proposal for the client to show.
 */
class WorkflowBuilderAssistController extends Controller
{
    public function __construct(private readonly WorkflowBuilderAssistant $assistant) {}

    public function suggestNodes(SuggestWorkflowBuilderNodesRequest $request, Workspace $workspace, WorkflowBuilderSession $session)
    {
        $this->authorizeSession($workspace, $session);

        return ApiResponse::success([
            'suggestions' => $this->assistant->suggestNodes($session, $request->validated('note')),
        ]);
    }

    public function configureNode(ConfigureWorkflowBuilderNodeRequest $request, Workspace $workspace, WorkflowBuilderSession $session)
    {
        $this->authorizeSession($workspace, $session);

        try {
            $proposal = $this->assistant->configureNode(
                $session,
                $request->validated('instruction'),
                $request->validated('type'),
                $request->validated('key'),
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::validationError(['node' => [$exception->getMessage()]], $exception->getMessage());
        }

        return ApiResponse::success(['proposal' => $proposal]);
    }

    public function explain(Workspace $workspace, WorkflowBuilderSession $session)
    {
        $this->authorizeSession($workspace, $session);

        if ($session->currentGraph()['nodes'] === []) {
            return ApiResponse::validationError(['draft' => ['The draft has no nodes to explain yet.']], 'The draft has no nodes to explain yet.');
        }

        return ApiResponse::success(['explanation' => $this->assistant->explain($session)]);
    }

    public function suggestImprovements(Workspace $workspace, WorkflowBuilderSession $session)
    {
        $this->authorizeSession($workspace, $session);

        if ($session->currentGraph()['nodes'] === []) {
            return ApiResponse::validationError(['draft' => ['The draft has no nodes to review yet.']], 'The draft has no nodes to review yet.');
        }

        return ApiResponse::success(['improvements' => $this->assistant->suggestImprovements($session)]);
    }

    private function authorizeSession(Workspace $workspace, WorkflowBuilderSession $session): void
    {
        $this->requirePermission(Permission::WorkflowBuilderUse);
        $this->ensureBelongsToWorkspace($workspace, $session);
    }
}
