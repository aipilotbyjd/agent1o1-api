<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\V1\Catalog\BulkAppsRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Catalog\MoveAppRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Catalog\SetNodeStatusRequest;
use App\Http\Requests\Api\SuperAdmin\V1\Catalog\UpdateAppRequest;
use App\Http\Requests\Api\SuperAdmin\V1\ReasonRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Connectors\Connector;
use App\Models\Nodes\CatalogFunction;
use App\Models\Nodes\CatalogVendor;
use App\Models\Nodes\NodeCategory;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Services\SuperAdmin\Catalog\SuperAdminAppService;
use Illuminate\Http\Request;

/**
 * Integrations as one list: the app in the editor's Apps panel and its
 * connector together, with who uses it, its history, and its nodes.
 */
class SuperAdminAppController extends Controller
{
    public function __construct(private readonly SuperAdminAppService $apps) {}

    public function index(Request $request)
    {
        return ApiResponse::success([
            'apps' => $this->apps->list($request->only(['q', 'function', 'vendor', 'state', 'status']))->all(),
            'functions' => CatalogFunction::query()->where('is_system', false)->orderBy('sort_order')->get()->map->only(['slug', 'label'])->all(),
            'vendors' => CatalogVendor::query()->orderBy('sort_order')->get()->map->only(['slug', 'label'])->all(),
            'icons' => NodeCategory::query()->where('kind', 'app')->whereNotNull('icon')->distinct()->orderBy('icon')->pluck('icon')->all(),
        ]);
    }

    public function show(NodeCategory $category)
    {
        return ApiResponse::success($this->apps->show($this->app($category)));
    }

    public function update(UpdateAppRequest $request, NodeCategory $category)
    {
        $category = $this->app($category);
        $changed = $this->apps->update($request->user('super-admin'), $category, $request->changes(), $request->validated('reason'));

        return ApiResponse::success($this->apps->show($category->refresh()), $changed === [] ? 'Nothing to change.' : 'App updated.');
    }

    public function impact(NodeCategory $category)
    {
        return ApiResponse::success($this->apps->impact($this->app($category)));
    }

    public function history(NodeCategory $category)
    {
        return ApiResponse::success($this->apps->history($this->app($category)));
    }

    public function move(MoveAppRequest $request, NodeCategory $category)
    {
        $this->apps->move($request->user('super-admin'), $this->app($category), $request->validated('direction'), $request->validated('reason'));

        return ApiResponse::success($this->apps->show($category->refresh()), 'App moved.');
    }

    public function revert(ReasonRequest $request, NodeCategory $category, string $log)
    {
        $category = $this->app($category);
        $this->apps->revert($request->user('super-admin'), $category, SuperAdminAuditLog::query()->findOrFail($log), $request->validated('reason'));

        return ApiResponse::success($this->apps->show($category->refresh()), 'Change reverted.');
    }

    public function bulk(BulkAppsRequest $request)
    {
        return ApiResponse::success(
            $this->apps->bulk($request->user('super-admin'), $request->validated('ids'), $request->validated('action'), $request->validated('value'), $request->validated('reason')),
            'Apps updated.',
        );
    }

    public function nodes(NodeCategory $category)
    {
        return ApiResponse::success($this->apps->nodes($this->app($category)));
    }

    public function setNodeStatus(SetNodeStatusRequest $request, NodeCategory $category, string $type)
    {
        $result = $this->apps->setNodeStatus($request->user('super-admin'), $this->app($category), $type, $request->validated('status'), $request->validated('note'), $request->validated('reason'));

        return ApiResponse::success($result, 'Node updated.');
    }

    /**
     * Only app categories are managed here; a core one is a 404.
     */
    private function app(NodeCategory $category): NodeCategory
    {
        abort_unless($category->kind === 'app', 404, 'Only apps are managed here.');

        return $category;
    }
}
