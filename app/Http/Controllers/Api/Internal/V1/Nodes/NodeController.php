<?php

namespace App\Http\Controllers\Api\Internal\V1\Nodes;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\V1\Nodes\StoreCustomNodeRequest;
use App\Http\Requests\Api\Internal\V1\Nodes\UpdateCustomNodeRequest;
use App\Http\Resources\Api\Internal\V1\Nodes\NodeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Nodes\CustomNode;
use App\Models\Runs\NodeRun;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NodeController extends Controller
{
    private const int RECENTLY_USED_LIMIT = 6;

    public function __construct(private readonly NodeRegistry $registry) {}

    /**
     * List the built-in catalog plus this workspace's custom nodes, optionally
     * filtered by category slug and/or a name/type/description search term.
     */
    public function index(Request $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::NodeView);

        $category = $request->query('category');
        $search = $request->query('search');

        $builtins = collect($this->registry->catalog())
            ->map(fn (array $node): array => [...$node, 'is_custom' => false])
            ->when($category, fn ($nodes) => $nodes->where('category', $category))
            ->when($search, fn ($nodes) => $nodes->filter(fn (array $node): bool => $this->matchesSearch($node['type'], $node['name'], $node['description'], $search)));

        $custom = $workspace->customNodes()
            ->with('category')
            ->where('is_active', true)
            ->when($category, fn ($query) => $query->whereHas('category', fn ($q) => $q->where('slug', $category)))
            ->when($search, fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('type', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")))
            ->get();

        return ApiResponse::success([
            'nodes' => $builtins->concat(NodeResource::collection($custom)->resolve())->values(),
        ]);
    }

    /**
     * The global built-in catalog only: no workspace, no custom nodes.
     */
    public function globalCatalog(Request $request)
    {
        $category = $request->query('category');
        $search = $request->query('search');

        $nodes = collect($this->registry->catalog())
            ->when($category, fn ($nodes) => $nodes->where('category', $category))
            ->when($search, fn ($nodes) => $nodes->filter(fn (array $node): bool => $this->matchesSearch($node['type'], $node['name'], $node['description'], $search)))
            ->values();

        return ApiResponse::success(['nodes' => $nodes]);
    }

    /**
     * Just this workspace's custom nodes: no built-ins merged in.
     */
    public function custom(Workspace $workspace)
    {
        $this->requirePermission(Permission::NodeView);

        $nodes = $workspace->customNodes()->with('category')->where('is_active', true)->orderBy('name')->get();

        return ApiResponse::success(['nodes' => NodeResource::collection($nodes)]);
    }

    /**
     * The nodes this workspace has actually run, most-used first (from real
     * `NodeRun` history). Falls back to the first built-ins, ordered by
     * category, when the workspace has no run history yet.
     */
    public function recentlyUsed(Workspace $workspace)
    {
        $this->requirePermission(Permission::NodeView);

        $usedTypes = NodeRun::query()
            ->select('type')
            ->selectRaw('count(*) as usage_count')
            ->whereHas('run', fn ($query) => $query->where('workspace_id', $workspace->id))
            ->groupBy('type')
            ->orderByDesc('usage_count')
            ->limit(self::RECENTLY_USED_LIMIT)
            ->pluck('usage_count', 'type');

        $catalog = collect($this->registry->catalog())->keyBy('type');

        if ($usedTypes->isEmpty()) {
            $nodes = $catalog->take(self::RECENTLY_USED_LIMIT)->values();

            return ApiResponse::success(['nodes' => $nodes, 'is_default' => true]);
        }

        $nodes = $usedTypes->keys()
            ->map(fn (string $type) => $catalog->get($type))
            ->filter()
            ->values();

        return ApiResponse::success(['nodes' => $nodes, 'is_default' => false]);
    }

    private function matchesSearch(string $type, string $name, string $description, string $search): bool
    {
        $needle = Str::lower($search);

        return Str::contains(Str::lower($type), $needle)
            || Str::contains(Str::lower($name), $needle)
            || Str::contains(Str::lower($description), $needle);
    }

    public function show(Workspace $workspace, CustomNode $node)
    {
        $this->requirePermission(Permission::NodeView);
        $this->ensureBelongsToWorkspace($workspace, $node);

        return ApiResponse::success(['node' => NodeResource::make($node->load('category'))]);
    }

    public function store(StoreCustomNodeRequest $request, Workspace $workspace)
    {
        $this->requirePermission(Permission::NodeManage);

        $node = $workspace->customNodes()->create([
            ...$request->validated(),
            'type' => $this->uniqueType($workspace, $request->validated('name')),
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::created(['node' => NodeResource::make($node->load('category'))], 'Node created.');
    }

    public function update(UpdateCustomNodeRequest $request, Workspace $workspace, CustomNode $node)
    {
        $this->requirePermission(Permission::NodeManage);
        $this->ensureBelongsToWorkspace($workspace, $node);

        $node->update($request->validated());

        return ApiResponse::success(['node' => NodeResource::make($node->load('category'))], 'Node updated.');
    }

    public function destroy(Workspace $workspace, CustomNode $node)
    {
        $this->requirePermission(Permission::NodeManage);
        $this->ensureBelongsToWorkspace($workspace, $node);

        $node->delete();

        return ApiResponse::noContent();
    }

    private function uniqueType(Workspace $workspace, string $name): string
    {
        $base = Str::slug($name, '_');
        $type = $base;
        $suffix = 1;

        while ($workspace->customNodes()->where('type', $type)->exists()) {
            $type = $base.'_'.++$suffix;
        }

        return $type;
    }
}
