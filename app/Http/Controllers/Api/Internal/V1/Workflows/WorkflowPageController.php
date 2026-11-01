<?php

namespace App\Http\Controllers\Api\Internal\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Workflows\StoreWorkflowPageRequest;
use App\Http\Requests\Api\Internal\V1\Workflows\UpdateWorkflowPageRequest;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowPageListResource;
use App\Http\Resources\Api\Internal\V1\Workflows\WorkflowPageResource;
use App\Http\Responses\ApiResponse;
use App\Models\Templates\PageTemplate;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowPage;
use App\Models\Workflows\WorkflowPageEvent;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\Pages\PageInsights;
use App\Services\Workflows\Pages\PagePresenter;
use App\Services\Workflows\Pages\WorkflowPages;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Workflow pages: see `WorkflowPage`. Members with `page.view` can open and
 * use a published page from inside the app; building, publishing and sharing
 * one takes `page.manage`.
 */
class WorkflowPageController extends Controller
{
    public function __construct(
        private readonly WorkflowPages $pages,
        private readonly PagePresenter $presenter,
        private readonly PageInsights $insights,
    ) {}

    public function index(Request $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::PageView);

        $pages = $workspace->workflowPages()
            ->with(['workflow' => fn ($query) => $query->withTrashed()])
            ->withCount(['submissions as runs_count' => fn (Builder $query) => $query->where('is_test', false)])
            ->withMax('submissions', 'created_at')
            ->when($request->query('workflow_id'), fn (Builder $query, string $workflowId) => $query->where('workflow_id', $workflowId))
            ->latest('updated_at')
            ->get();

        return ApiResponse::success(['pages' => WorkflowPageListResource::collection($pages)]);
    }

    public function store(StoreWorkflowPageRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::PageManage);

        $workflow = $this->workflowIn($workspace, $request->validated('workflow_id'));

        $template = null;

        if ($request->filled('template_id')) {
            $this->requirePermission(Permission::TemplateView);
            $template = PageTemplate::query()->visibleTo($workspace)->findOrFail($request->validated('template_id'));
        }

        $page = $this->pages->create($workflow, $request->user(), $request->validated('name'), $request->validated('description'), $template);

        return ApiResponse::created(['page' => $this->full($page)], 'Page created.');
    }

    public function show(Workspace $workspace, WorkflowPage $page)
    {
        $this->requirePermission(Permission::PageView);
        $this->ensureBelongsToWorkspace($workspace, $page);

        return ApiResponse::success(['page' => $this->full($page)]);
    }

    public function update(UpdateWorkflowPageRequest $request, Workspace $workspace, WorkflowPage $page)
    {
        $this->requirePermission(Permission::PageManage);
        $this->ensureBelongsToWorkspace($workspace, $page);

        $changes = $request->validated();

        if (array_key_exists('workflow_id', $changes)) {
            $this->workflowIn($workspace, $changes['workflow_id']);
        }

        $page = $this->pages->update($page, $changes);

        return ApiResponse::success(['page' => $this->full($page)], 'Page saved.');
    }

    public function destroy(Workspace $workspace, WorkflowPage $page)
    {
        $this->requirePermission(Permission::PageManage);
        $this->ensureBelongsToWorkspace($workspace, $page);

        $this->pages->delete($page);

        return ApiResponse::success(null, 'Page deleted.');
    }

    public function duplicate(Request $request, Workspace $workspace, WorkflowPage $page)
    {
        $this->requirePermission(Permission::PageManage);
        $this->ensureBelongsToWorkspace($workspace, $page);

        return ApiResponse::created(['page' => $this->full($this->pages->duplicate($page, $request->user()))], 'Page duplicated.');
    }

    public function publish(Request $request, Workspace $workspace, WorkflowPage $page)
    {
        $this->requirePermission(Permission::PageManage);
        $this->ensureBelongsToWorkspace($workspace, $page);

        $page = $this->pages->publish($page, $request->user());

        return ApiResponse::success(['page' => $this->full($page)], 'Page published.');
    }

    public function unpublish(Workspace $workspace, WorkflowPage $page)
    {
        $this->requirePermission(Permission::PageManage);
        $this->ensureBelongsToWorkspace($workspace, $page);

        return ApiResponse::success(['page' => $this->full($this->pages->unpublish($page))], 'Page unpublished.');
    }

    public function rotateLink(Workspace $workspace, WorkflowPage $page)
    {
        $this->requirePermission(Permission::PageManage);
        $this->ensureBelongsToWorkspace($workspace, $page);

        return ApiResponse::success(['page' => $this->full($this->pages->rotateLink($page))], 'New link created. The old one no longer works.');
    }

    /**
     * The page as a member using it sees it: the published page, or with
     * `?draft=1` the draft, for someone who can edit it.
     */
    public function view(Request $request, Workspace $workspace, WorkflowPage $page)
    {
        $this->requirePermission(Permission::PageView);
        $this->ensureBelongsToWorkspace($workspace, $page);

        $draft = $request->boolean('draft');

        if ($draft) {
            $this->requirePermission(Permission::PageManage);
        }

        $visitor = $this->insights->visitor($page, $request, $request->user());
        $variant = $draft ? 'a' : $this->insights->variantFor($page, $visitor);
        $content = $page->contentFor($variant, published: ! $draft);

        abort_if($content === null, 404, 'This page has not been published yet.');
        abort_if(! $draft && ! $page->is_enabled, 404, 'This page is turned off.');

        if (! $draft) {
            $this->insights->record($page, WorkflowPageEvent::View, $variant, $visitor);
        }

        return ApiResponse::success(['view' => $this->presenter->present($page, $content)]);
    }

    /**
     * A member started filling in the published page.
     */
    public function event(Request $request, Workspace $workspace, WorkflowPage $page)
    {
        $this->requirePermission(Permission::PageView);
        $this->ensureBelongsToWorkspace($workspace, $page);
        $request->validate(['type' => ['required', 'in:'.WorkflowPageEvent::Start]]);

        $visitor = $this->insights->visitor($page, $request, $request->user());
        $this->insights->record($page, WorkflowPageEvent::Start, $this->insights->variantFor($page, $visitor), $visitor);

        return ApiResponse::noContent();
    }

    /**
     * Views, starts, runs and how they ended over the last `days` days, by
     * day and per version while an A/B test runs.
     */
    public function insights(Request $request, Workspace $workspace, WorkflowPage $page)
    {
        $this->requirePermission(Permission::PageView);
        $this->ensureBelongsToWorkspace($workspace, $page);

        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'in:7,30,90'],
            'include_tests' => ['nullable', 'boolean'],
        ]);

        return ApiResponse::success([
            'insights' => $this->insights->summary($page, (int) ($validated['days'] ?? 30), $request->boolean('include_tests')),
        ]);
    }

    public function createVariant(Workspace $workspace, WorkflowPage $page)
    {
        $this->requirePermission(Permission::PageManage);
        $this->ensureBelongsToWorkspace($workspace, $page);

        return ApiResponse::success(['page' => $this->full($this->pages->createVariant($page))], 'Version B created.');
    }

    public function removeVariant(Workspace $workspace, WorkflowPage $page)
    {
        $this->requirePermission(Permission::PageManage);
        $this->ensureBelongsToWorkspace($workspace, $page);

        return ApiResponse::success(['page' => $this->full($this->pages->removeVariant($page))], 'Test ended. Everyone sees the main page.');
    }

    public function promoteVariant(Workspace $workspace, WorkflowPage $page)
    {
        $this->requirePermission(Permission::PageManage);
        $this->ensureBelongsToWorkspace($workspace, $page);

        return ApiResponse::success(['page' => $this->full($this->pages->promoteVariant($page))], 'Version B is now the main page. Publish to make it live.');
    }

    private function workflowIn(Workspace $workspace, string $workflowId): Workflow
    {
        return $workspace->workflows()->visible()->findOrFail($workflowId);
    }

    private function full(WorkflowPage $page): WorkflowPageResource
    {
        return WorkflowPageResource::make($page->fresh()->load([
            'workflow' => fn ($query) => $query->withTrashed(),
            'publisher:id,name',
            'creator:id,name',
        ]));
    }
}
