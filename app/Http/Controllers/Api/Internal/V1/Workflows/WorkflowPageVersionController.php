<?php

namespace App\Http\Controllers\Api\Internal\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowPageResource;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowPageVersionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\WorkflowPage;
use App\Models\Workflows\WorkflowPageVersion;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\Pages\PageVersions;
use Illuminate\Http\Request;

/**
 * A page's version history: see `PageVersions`. Building pages takes
 * `page.manage`, and so does looking back through their history.
 */
class WorkflowPageVersionController extends Controller
{
    public function __construct(private readonly PageVersions $versions) {}

    public function index(Workspace $workspace, WorkflowPage $page)
    {
        $this->authorizePage($workspace, $page);

        $versions = $page->versions()->with('creator:id,name')->orderByDesc('number')->get();

        return ApiResponse::success(['versions' => WorkflowPageVersionResource::collection($versions)]);
    }

    public function show(Workspace $workspace, WorkflowPage $page, WorkflowPageVersion $version)
    {
        $this->authorizePage($workspace, $page);
        abort_if($version->workflow_page_id !== $page->id, 404);

        return ApiResponse::success(['version' => WorkflowPageVersionResource::make($version->load('creator:id,name'))->withContent()]);
    }

    /**
     * Saves the drafts as they are now, with an optional label, also used
     * by the builder before it applies an AI design.
     */
    public function store(Request $request, Workspace $workspace, WorkflowPage $page)
    {
        $this->authorizePage($workspace, $page);

        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:120'],
            'kind' => ['nullable', 'in:'.WorkflowPageVersion::Saved.','.WorkflowPageVersion::BeforeGeneration],
        ]);

        $version = $this->versions->snapshot($page, $validated['kind'] ?? WorkflowPageVersion::Saved, $request->user(), $validated['label'] ?? null);

        return ApiResponse::created(['version' => WorkflowPageVersionResource::make($version->load('creator:id,name'))], 'Version saved.');
    }

    public function restore(Request $request, Workspace $workspace, WorkflowPage $page, WorkflowPageVersion $version)
    {
        $this->authorizePage($workspace, $page);

        $page = $this->versions->restore($page, $version, $request->user());

        return ApiResponse::success([
            'page' => WorkflowPageResource::make($page->fresh()->load(['workflow' => fn ($query) => $query->withTrashed(), 'publisher:id,name', 'creator:id,name'])),
        ], "Version {$version->number} restored to the draft. Publish to make it live.");
    }

    private function authorizePage(Workspace $workspace, WorkflowPage $page): void
    {
        $this->requirePermission(Permission::PageManage);
        $this->ensureBelongsToWorkspace($workspace, $page);
    }
}
