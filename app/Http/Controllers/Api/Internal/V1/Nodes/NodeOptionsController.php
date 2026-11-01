<?php

namespace App\Http\Controllers\Api\Internal\V1\Nodes;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Nodes\LoadNodeOptionsRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\NodeOptions\NodeOptionsService;

/**
 * The live choices for one node field's dropdown: see `NodeOptionsService`.
 * Needs connector access as well as node access, since most lists are read
 * through a connected account.
 */
class NodeOptionsController extends Controller
{
    public function __invoke(LoadNodeOptionsRequest $request, Workspace $workspace, NodeOptionsService $options)
    {
        $this->requirePermission(Permission::NodeView);
        $this->requirePermission(Permission::ConnectorView);

        $page = $options->load(
            $workspace,
            $request->user(),
            $request->validated('type'),
            $request->validated('field'),
            $request->validated('config') ?? [],
            $request->validated('search'),
            $request->validated('cursor'),
        );

        return ApiResponse::success($page->toArray());
    }
}
