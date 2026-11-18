<?php

namespace App\Http\Controllers\Api\Internal\V1\Templates;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Templates\ListTemplatesRequest;
use App\Http\Resources\Api\Internal\V1\Templates\TemplateCatalogItemResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\Workspace;
use App\Services\Templates\TemplateCatalog;
use Illuminate\Http\JsonResponse;

/**
 * Every template the workspace can see, of every type, as one paginated
 * list: the onboarding gallery scrolls through it page by page.
 */
class TemplateCatalogController extends Controller
{
    public function index(ListTemplatesRequest $request, Workspace $workspace, TemplateCatalog $catalog): JsonResponse
    {
        $this->requirePermission(Permission::TemplateView);

        $templates = $catalog->paginate(
            $workspace,
            $request->types(),
            $request->validated('search'),
            (int) $request->validated('per_page', 12),
        );

        return ApiResponse::paginated(TemplateCatalogItemResource::collection($templates));
    }
}
