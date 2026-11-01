<?php

namespace App\Http\Controllers\Api\Internal\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowPageGenerationResource;
use App\Http\Responses\ApiResponse;
use App\Jobs\Pages\GeneratePageJob;
use App\Models\Workflows\WorkflowPage;
use App\Models\Workflows\WorkflowPageGeneration;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\CreditGate;
use Illuminate\Http\Request;

/**
 * Asking AI to design or rework a page: see `GeneratePageJob`. The request
 * returns straight away; the builder hears `page.generation` on the page's
 * channel, or polls the generation, and then shows what was made.
 */
class WorkflowPageGenerationController extends Controller
{
    public function store(Request $request, Workspace $workspace, WorkflowPage $page, CreditGate $creditGate)
    {
        $this->authorizePage($workspace, $page);
        $creditGate->assertCanStartRun($workspace);

        $validated = $request->validate([
            'prompt' => ['required', 'string', 'min:3', 'max:4000'],
            'variant' => ['nullable', 'in:a,b'],
            'keep_theme' => ['nullable', 'boolean'],
        ]);

        $variant = $validated['variant'] ?? 'a';
        abort_if($variant === 'b' && $page->variant_content === null, 409, 'This page has no version B.');

        $generation = $page->generations()->create([
            'user_id' => $request->user()->id,
            'prompt' => trim($validated['prompt']),
            'variant' => $variant,
            'keep_theme' => $request->boolean('keep_theme', true),
            'status' => WorkflowPageGeneration::Pending,
        ]);

        GeneratePageJob::dispatch($generation->id);

        return ApiResponse::success(['generation' => WorkflowPageGenerationResource::make($generation->refresh())], 'Designing your page…', 202);
    }

    public function show(Workspace $workspace, WorkflowPage $page, WorkflowPageGeneration $generation)
    {
        $this->authorizePage($workspace, $page);
        abort_if($generation->workflow_page_id !== $page->id, 404);

        return ApiResponse::success(['generation' => WorkflowPageGenerationResource::make($generation)]);
    }

    private function authorizePage(Workspace $workspace, WorkflowPage $page): void
    {
        $this->requirePermission(Permission::PageManage);
        $this->ensureBelongsToWorkspace($workspace, $page);
    }
}
