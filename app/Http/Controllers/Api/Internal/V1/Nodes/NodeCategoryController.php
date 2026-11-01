<?php

namespace App\Http\Controllers\Api\Internal\V1\Nodes;

use App\Enums\NodeCategory as NodeCategoryEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Nodes\UpdateNodeCategoryPinRequest;
use App\Http\Resources\Api\Internal\V1\Nodes\NodeCategoryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Nodes\NodeCategory;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class NodeCategoryController extends Controller
{
    public function __construct(private readonly NodeRegistry $registry) {}

    /**
     * `nodes_count` counts built-in nodes only, same as `show()` (Triggers &
     * Events also counts the app readers it lists). Pass
     * `?include_nodes=1` to also get each category's nodes inline.
     */
    public function index(Request $request)
    {
        $includeNodes = $request->boolean('include_nodes');
        $catalog = collect($this->registry->catalog())->groupBy('category');

        $categories = NodeCategory::query()
            ->withExists(['pinnedByUsers as is_pinned' => fn ($query) => $query->whereKey($request->user()->id)])
            ->orderBy('sort_order')
            ->get()
            ->map(function (NodeCategory $category) use ($catalog, $includeNodes): array {
                $nodes = $this->nodesFor($category, $catalog->get($category->slug, collect())->values());

                return [
                    ...NodeCategoryResource::make($category)->resolve(),
                    'nodes_count' => $nodes->where('listed', true)->count(),
                    ...($includeNodes ? ['nodes' => $nodes] : []),
                ];
            });

        return ApiResponse::success(['categories' => $categories]);
    }

    /**
     * Pins or unpins an app for the signed-in user, so "Your apps" follows
     * their account across browsers. Only integrations can be pinned.
     */
    public function pin(UpdateNodeCategoryPinRequest $request, NodeCategory $nodeCategory)
    {
        abort_unless($nodeCategory->kind === 'app', 404, 'Only apps can be pinned.');

        if ($request->boolean('is_pinned')) {
            $nodeCategory->pinnedByUsers()->syncWithoutDetaching([$request->user()->id]);
        } else {
            $nodeCategory->pinnedByUsers()->detach($request->user()->id);
        }

        return ApiResponse::success(['is_pinned' => $request->boolean('is_pinned')], 'Pin updated.');
    }

    /**
     * A single category plus the built-in nodes filed under it. Custom nodes
     * aren't included here: this route isn't workspace-scoped, see
     * `GET /workspaces/{workspace}/nodes?category=...` for those.
     */
    public function show(Request $request, NodeCategory $nodeCategory)
    {
        $nodeCategory->loadExists(['pinnedByUsers as is_pinned' => fn ($query) => $query->whereKey($request->user()->id)]);

        $nodes = $this->nodesFor($nodeCategory, collect($this->registry->catalog())
            ->where('category', $nodeCategory->slug)
            ->values());

        return ApiResponse::success([
            'category' => NodeCategoryResource::make($nodeCategory),
            'nodes' => $nodes,
            'nodes_count' => $nodes->where('listed', true)->count(),
        ]);
    }

    /**
     * Triggers & Events lists every node that can start a flow, like
     * Gumloop's Triggers section: the dedicated trigger nodes, then each
     * app's reader that can act as one (also still listed under its app).
     *
     * @param  Collection<int, array<string, mixed>>  $nodes
     * @return Collection<int, array<string, mixed>>
     */
    private function nodesFor(NodeCategory $category, Collection $nodes): Collection
    {
        if ($category->slug !== NodeCategoryEnum::TriggersEvents->value) {
            return $nodes;
        }

        return $nodes->concat($this->registry->triggerCatalog())->values();
    }
}
