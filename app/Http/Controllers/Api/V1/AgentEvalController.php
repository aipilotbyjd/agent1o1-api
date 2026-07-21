<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AgentEvalRunResource;
use App\Http\Resources\V1\AgentEvalSuiteResource;
use App\Models\Agent;
use App\Models\AgentEvalSuite;
use App\Models\Workspace;
use App\Services\Agent\AgentEvalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Agent eval/testing framework (roadmap item 9): manage test suites of
 * input+assertion cases and run them against an agent for a pass/fail report.
 */
class AgentEvalController extends Controller
{
    public function __construct(private readonly AgentEvalService $evals) {}

    public function index(Request $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            return $denied;
        }

        $evalSuites = $agent->evalSuites()
            ->withCount('cases')
            ->with('runs')
            ->latest()
            ->paginate((int) $request->query('per_page', 25));

        return $this->paginatedResponse('Eval suites retrieved.', AgentEvalSuiteResource::collection($evalSuites));
    }

    public function store(Request $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentUpdate)) {
            return $denied;
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'cases' => ['nullable', 'array'],
            'cases.*.name' => ['required_with:cases', 'string', 'max:255'],
            'cases.*.input' => ['required_with:cases', 'string'],
            'cases.*.assertions' => ['required_with:cases', 'array', 'min:1'],
            'cases.*.assertions.*.type' => ['required', 'string', 'in:contains,not_contains,equals,regex,llm_rubric'],
            'cases.*.assertions.*.value' => ['required', 'string'],
        ]);

        $evalSuite = $agent->evalSuites()->create([
            'workspace_id' => $agent->workspace_id,
            'created_by' => $request->user()?->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        foreach ($data['cases'] ?? [] as $i => $case) {
            $evalSuite->cases()->create([
                'name' => $case['name'],
                'input' => $case['input'],
                'assertions' => $case['assertions'],
                'sort_order' => $i,
            ]);
        }

        return $this->successResponse(
            'Eval suite created.',
            new AgentEvalSuiteResource($evalSuite->load('cases')),
            201,
        );
    }

    public function show(Request $request, Workspace $workspace, Agent $agent, AgentEvalSuite $evalSuite): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            return $denied;
        }

        $this->ensureSuiteBelongsToAgent($agent, $evalSuite);

        return $this->successResponse(
            'Eval suite retrieved.',
            new AgentEvalSuiteResource($evalSuite->load(['cases', 'runs'])),
        );
    }

    public function destroy(Request $request, Workspace $workspace, Agent $agent, AgentEvalSuite $evalSuite): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentUpdate)) {
            return $denied;
        }

        $this->ensureSuiteBelongsToAgent($agent, $evalSuite);
        $evalSuite->delete();

        return $this->successResponse('Eval suite deleted.');
    }

    public function addCase(Request $request, Workspace $workspace, Agent $agent, AgentEvalSuite $evalSuite): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentUpdate)) {
            return $denied;
        }

        $this->ensureSuiteBelongsToAgent($agent, $evalSuite);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'input' => ['required', 'string'],
            'assertions' => ['required', 'array', 'min:1'],
            'assertions.*.type' => ['required', 'string', 'in:contains,not_contains,equals,regex,llm_rubric'],
            'assertions.*.value' => ['required', 'string'],
        ]);

        $case = $evalSuite->cases()->create([
            'name' => $data['name'],
            'input' => $data['input'],
            'assertions' => $data['assertions'],
            'sort_order' => (int) ($evalSuite->cases()->max('sort_order') ?? -1) + 1,
        ]);

        return $this->successResponse(
            'Eval case added.',
            new AgentEvalSuiteResource($evalSuite->fresh('cases')),
            201,
        );
    }

    public function destroyCase(Request $request, Workspace $workspace, Agent $agent, AgentEvalSuite $evalSuite, string $caseId): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentUpdate)) {
            return $denied;
        }

        $this->ensureSuiteBelongsToAgent($agent, $evalSuite);
        $evalSuite->cases()->whereKey($caseId)->delete();

        return $this->successResponse('Eval case deleted.');
    }

    /**
     * Run the suite against the agent and return the graded report.
     */
    public function run(Request $request, Workspace $workspace, Agent $agent, AgentEvalSuite $evalSuite): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentRun)) {
            return $denied;
        }

        $this->ensureSuiteBelongsToAgent($agent, $evalSuite);

        if ($evalSuite->cases()->count() === 0) {
            return $this->errorResponse('This suite has no cases to run.', 422);
        }

        $run = $this->evals->run($evalSuite, $request->user());

        return $this->successResponse('Eval run completed.', new AgentEvalRunResource($run));
    }

    public function runs(Request $request, Workspace $workspace, Agent $agent, AgentEvalSuite $evalSuite): JsonResponse
    {
        if ($denied = $this->requirePermission(Permission::AgentView)) {
            return $denied;
        }

        $this->ensureSuiteBelongsToAgent($agent, $evalSuite);

        $runs = $evalSuite->runs()->paginate((int) $request->query('per_page', 25));

        return $this->paginatedResponse('Eval runs retrieved.', AgentEvalRunResource::collection($runs));
    }

    private function ensureSuiteBelongsToAgent(Agent $agent, AgentEvalSuite $evalSuite): void
    {
        abort_unless($evalSuite->agent_id === $agent->id, 404);
    }
}
