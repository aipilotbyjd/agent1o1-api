<?php

namespace App\Http\Controllers\Api\Public\V1\Workflows;

use App\Enums\Billing\PlanLimit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Public\V1\StoreWorkflowRequest;
use App\Http\Requests\Api\Public\V1\UpdateWorkflowRequest;
use App\Http\Resources\Api\Public\V1\WorkflowResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Workflow;
use App\Services\Billing\PlanLimitGate;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Workflow CRUD for integrators. Writes are gated on the `workflows:write`
 * key ability (see `routes/api/public/workflows.php`); this class assumes
 * that check has already happened, exactly as the internal controllers
 * assume their permission check has.
 *
 * `created_by` is null on anything created here: an API key belongs to a
 * workspace, not a person, and inventing an author would be a lie in the
 * audit trail.
 */
class WorkflowController extends Controller
{
    public function index(Request $request)
    {
        return ApiResponse::success([
            'workflows' => WorkflowResource::collection(
                $this->apiKeyWorkspace($request)->workflows()->visible()->latest()->get(),
            ),
        ]);
    }

    public function show(Request $request, Workflow $workflow)
    {
        $this->ensureBelongsToWorkspace($this->apiKeyWorkspace($request), $workflow);

        return ApiResponse::success(['workflow' => WorkflowResource::make($workflow)]);
    }

    public function store(StoreWorkflowRequest $request, PlanLimitGate $limits)
    {
        $workspace = $this->apiKeyWorkspace($request);
        $limits->assertCanCreate($workspace, PlanLimit::Workflows);

        $workflow = $workspace->workflows()->create([
            ...$request->validated(),
            'slug' => $request->validated('slug') ?: Str::slug($request->validated('name')).'-'.Str::random(6),
        ]);

        return ApiResponse::created(['workflow' => WorkflowResource::make($workflow)], 'Workflow created.');
    }

    public function update(UpdateWorkflowRequest $request, Workflow $workflow)
    {
        $this->ensureBelongsToWorkspace($this->apiKeyWorkspace($request), $workflow);

        $workflow->update($request->validated());

        return ApiResponse::success(['workflow' => WorkflowResource::make($workflow)], 'Workflow updated.');
    }

    public function destroy(Request $request, Workflow $workflow)
    {
        $this->ensureBelongsToWorkspace($this->apiKeyWorkspace($request), $workflow);

        $workflow->delete();

        return ApiResponse::noContent();
    }
}
