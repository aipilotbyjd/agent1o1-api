<?php

namespace App\Http\Controllers\Api\Internal\V1\Templates;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Templates\SavePageAsTemplateRequest;
use App\Http\Requests\Api\Internal\V1\Templates\UpdatePageTemplateRequest;
use App\Http\Resources\Api\Internal\V1\Templates\PageTemplateResource;
use App\Http\Responses\ApiResponse;
use App\Models\Templates\PageTemplate;
use App\Models\Workflows\WorkflowPage;
use App\Models\Workspaces\Workspace;
use Illuminate\Support\Str;

/**
 * Page templates: see `PageTemplate`. A template is used by creating a page
 * with its `template_id`, since a page always needs a workflow to run.
 */
class PageTemplateController extends Controller
{
    public function index(Workspace $workspace)
    {
        $this->requirePermission(Permission::TemplateView);

        $templates = PageTemplate::query()
            ->visibleTo($workspace)
            ->when(request('category'), fn ($query, $category) => $query->where('category', $category))
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->latest()
            ->get();

        return ApiResponse::success(['page_templates' => PageTemplateResource::collection($templates)]);
    }

    public function show(Workspace $workspace, PageTemplate $pageTemplate)
    {
        $this->requirePermission(Permission::TemplateView);
        $this->ensureVisibleToWorkspace($workspace, $pageTemplate);

        return ApiResponse::success(['page_template' => PageTemplateResource::make($pageTemplate)]);
    }

    public function update(UpdatePageTemplateRequest $request, Workspace $workspace, PageTemplate $pageTemplate)
    {
        $this->requirePermission(Permission::TemplateManage);
        $this->ensureBelongsToWorkspace($workspace, $pageTemplate);

        $pageTemplate->update($request->validated());

        return ApiResponse::success(['page_template' => PageTemplateResource::make($pageTemplate)], 'Template updated successfully.');
    }

    public function destroy(Workspace $workspace, PageTemplate $pageTemplate)
    {
        $this->requirePermission(Permission::TemplateManage);
        $this->ensureBelongsToWorkspace($workspace, $pageTemplate);

        $pageTemplate->delete();

        return ApiResponse::noContent();
    }

    /**
     * Snapshot a page's draft layout and theme into a new workspace-owned
     * template. Its sharing settings stay with the page.
     */
    public function storeFromPage(SavePageAsTemplateRequest $request, Workspace $workspace, WorkflowPage $page)
    {
        $this->requirePermission(Permission::TemplateManage);
        $this->ensureBelongsToWorkspace($workspace, $page);

        $template = $workspace->pageTemplates()->create([
            ...$request->validated(),
            'slug' => Str::slug($request->validated('name')).'-'.Str::random(6),
            'source_page_id' => $page->id,
            'content' => $page->content,
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::created(['page_template' => PageTemplateResource::make($template)], 'Page saved as template.');
    }

    private function ensureVisibleToWorkspace(Workspace $workspace, PageTemplate $template): void
    {
        $visible = $template->workspace_id === $workspace->id
            || ($template->workspace_id === null && $template->isPublic());

        abort_unless($visible, 404);
    }
}
