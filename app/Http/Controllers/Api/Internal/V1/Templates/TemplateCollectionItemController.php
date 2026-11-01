<?php

namespace App\Http\Controllers\Api\Internal\V1\Templates;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Templates\ReorderTemplateCollectionItemsRequest;
use App\Http\Requests\Api\Internal\V1\Templates\StoreTemplateCollectionItemRequest;
use App\Http\Resources\Api\Internal\V1\Templates\TemplateCollectionItemResource;
use App\Http\Responses\ApiResponse;
use App\Models\Templates\TemplateCollection;
use App\Models\Templates\TemplateCollectionItem;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Relations\Relation;

class TemplateCollectionItemController extends Controller
{
    public function store(StoreTemplateCollectionItemRequest $request, Workspace $workspace, TemplateCollection $templateCollection)
    {
        $this->requirePermission(Permission::TemplateManage);
        $this->ensureBelongsToWorkspace($workspace, $templateCollection);

        $templatableClass = Relation::getMorphedModel($request->validated('templatable_type'));
        $templatable = $templatableClass::query()->visibleTo($workspace)->find($request->validated('templatable_id'));

        abort_if($templatable === null, 422, 'The selected template is not visible to this workspace.');

        $item = $templateCollection->items()->create([
            'templatable_type' => $request->validated('templatable_type'),
            'templatable_id' => $templatable->id,
            'position' => $request->validated('position') ?? $templateCollection->items()->max('position') + 1,
        ]);

        return ApiResponse::created(['item' => TemplateCollectionItemResource::make($item->load('templatable'))], 'Added to collection.');
    }

    public function destroy(Workspace $workspace, TemplateCollection $templateCollection, TemplateCollectionItem $item)
    {
        $this->requirePermission(Permission::TemplateManage);
        $this->ensureBelongsToWorkspace($workspace, $templateCollection);
        abort_if($item->collection_id !== $templateCollection->id, 404);

        $item->delete();

        return ApiResponse::noContent();
    }

    public function reorder(ReorderTemplateCollectionItemsRequest $request, Workspace $workspace, TemplateCollection $templateCollection)
    {
        $this->requirePermission(Permission::TemplateManage);
        $this->ensureBelongsToWorkspace($workspace, $templateCollection);

        foreach ($request->validated('items') as $item) {
            $templateCollection->items()->where('id', $item['id'])->update(['position' => $item['position']]);
        }

        return ApiResponse::success([
            'items' => TemplateCollectionItemResource::collection($templateCollection->items),
        ], 'Collection reordered.');
    }
}
