<?php

namespace App\Http\Controllers\Api\Internal\V1\Ai;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Internal\V1\Ai\ModelCatalogResource;
use App\Http\Responses\ApiResponse;
use App\Models\Ai\ModelCatalog;
use App\Services\Ai\WorkspaceModelView;
use Illuminate\Http\Request;

/**
 * The public model picker for agents/workflow "Ask AI" nodes, not
 * workspace-scoped, same as `Nodes\NodeController::globalCatalog()`. Which
 * real backend(s) actually serve an entry is never exposed here; see
 * `ModelCatalogResource` and `Services\Ai\ModelCatalogResolver`.
 *
 * `?workspace_id=` (one the caller belongs to) describes each entry from
 * that member's seat: see `WorkspaceModelView`.
 */
class ModelCatalogController extends Controller
{
    public function index(Request $request)
    {
        $workspaceId = $request->query('workspace_id');
        $view = is_string($workspaceId) && $request->user()->workspaces()->whereKey($workspaceId)->exists()
            ? WorkspaceModelView::forMember($workspaceId, $request->user()->id)
            : WorkspaceModelView::platform();

        $catalog = ModelCatalog::query()
            ->with(['routes' => fn ($query) => $query->where('is_enabled', true), 'minPlan'])
            ->where('is_active', true)
            ->where('is_internal', false)
            ->orderBy('sort_order')
            ->orderBy('display_name')
            ->get();

        return ApiResponse::success([
            'model_catalog' => $catalog->map(fn (ModelCatalog $entry) => ModelCatalogResource::make($entry)->withView($view)),
        ]);
    }
}
