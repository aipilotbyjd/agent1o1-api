<?php

namespace App\Http\Controllers\Api\Internal\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Workflows\StorePageSectionRequest;
use App\Http\Resources\Api\Internal\V1\Workflows\PageSectionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\PageSection;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\Pages\PageSchema;

/**
 * The workspace's library of saved page blocks: see `PageSection`. Anyone
 * who can build pages can save to it and use what's in it.
 */
class PageSectionController extends Controller
{
    public function __construct(private readonly PageSchema $schema) {}

    public function index(Workspace $workspace)
    {
        $this->requirePermission(Permission::PageManage);

        $sections = $workspace->pageSections()->with('creator:id,name')->latest('updated_at')->get();

        return ApiResponse::success(['sections' => PageSectionResource::collection($sections)]);
    }

    public function store(StorePageSectionRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::PageManage);

        // Only `block.type` has a rule; normalizing is what checks the rest.
        $block = $this->schema->normalize(['blocks' => [$request->input('block')]])['blocks'][0] ?? null;

        abort_if($block === null, 422, 'That block can’t be saved on its own.');

        $section = $workspace->pageSections()->create([
            'name' => $request->validated('name'),
            'block' => $block,
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::created(['section' => PageSectionResource::make($section->load('creator:id,name'))], 'Saved to your sections.');
    }

    public function update(StorePageSectionRequest $request, Workspace $workspace, PageSection $section)
    {
        $this->requirePermission(Permission::PageManage);
        $this->ensureBelongsToWorkspace($workspace, $section);

        $section->update($request->validated());

        return ApiResponse::success(['section' => PageSectionResource::make($section->load('creator:id,name'))]);
    }

    public function destroy(Workspace $workspace, PageSection $section)
    {
        $this->requirePermission(Permission::PageManage);
        $this->ensureBelongsToWorkspace($workspace, $section);

        $section->delete();

        return ApiResponse::noContent();
    }
}
