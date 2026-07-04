<?php

namespace App\Http\Controllers\Api\V1\WorkflowBuilder;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\WorkflowBuilder\BuildWorkflowRequest;
use App\Http\Requests\Api\V1\WorkflowBuilder\ConfigureNodeRequest;
use App\Http\Requests\Api\V1\WorkflowBuilder\ExplainRequest;
use App\Http\Requests\Api\V1\WorkflowBuilder\SuggestEnhancementsRequest;
use App\Http\Requests\Api\V1\WorkflowBuilder\SuggestNodesRequest;
use App\Http\Resources\V1\WorkflowResource;
use App\Models\Workspace;
use App\Services\WorkflowBuilder\EnhancementService;
use App\Services\WorkflowBuilder\ExplainerService;
use App\Services\WorkflowBuilder\GenerationService;
use App\Services\WorkflowBuilder\SuggestionService;
use Illuminate\Http\JsonResponse;

class GenerationController extends Controller
{
    public function __construct(
        private readonly GenerationService $generationService,
        private readonly ExplainerService $explainerService,
        private readonly SuggestionService $suggestionService,
        private readonly EnhancementService $enhancementService,
    ) {}

    public function build(BuildWorkflowRequest $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowCreate)) {
            return $denied;
        }

        $save = (bool) $request->validated('save', false);

        if ($save) {
            $workflow = $this->generationService->generateAndSave(
                $workspace,
                $request->user(),
                $request->validated('prompt')
            );

            return $this->successResponse('Workflow generated and saved.', new WorkflowResource($workflow), 201);
        }

        $draft = $this->generationService->generate($workspace, $request->validated('prompt'), $request->user());

        return $this->successResponse('Workflow draft generated.', $draft);
    }

    public function explain(ExplainRequest $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowView)) {
            return $denied;
        }

        $explanation = $this->explainerService->explain(
            $request->validated('nodes'),
            $request->validated('edges'),
        );

        return $this->successResponse('Workflow explained.', ['explanation' => $explanation]);
    }

    public function suggestNodes(SuggestNodesRequest $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowCreate)) {
            return $denied;
        }

        $suggestions = $this->suggestionService->suggestNodes(
            $request->validated('nodes'),
            $request->validated('edges'),
            $request->validated('goal'),
        );

        return $this->successResponse('Node suggestions generated.', ['suggestions' => $suggestions]);
    }

    public function configureNode(ConfigureNodeRequest $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowCreate)) {
            return $denied;
        }

        $result = $this->suggestionService->configureNode(
            $request->validated('node_type'),
            $request->validated('intent'),
        );

        return $this->successResponse('Node configuration generated.', $result);
    }

    public function suggestEnhancements(SuggestEnhancementsRequest $request, Workspace $workspace): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::WorkflowCreate)) {
            return $denied;
        }

        $suggestions = $this->enhancementService->suggestEnhancements(
            $request->validated('nodes'),
            $request->validated('edges'),
        );

        return $this->successResponse('Enhancement suggestions generated.', ['suggestions' => $suggestions]);
    }
}
