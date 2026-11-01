<?php

namespace App\Services\Workflows;

use App\Enums\NodeRunStatus;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Models\Workflows\WorkflowNode;
use App\Models\Workspaces\Workspace;

/**
 * What a node type's output is known to look like *in one workspace*, learnt
 * from the outputs its nodes have actually produced there: pinned data
 * first (a sample someone chose deliberately), then recent successful runs.
 * The same node type can return differently shaped data per account or
 * endpoint, so shapes are never shared across workspaces.
 *
 * Backs `InspectNodeOutputTool` and `DryRunner`'s simulated outputs.
 */
class NodeOutputShapes
{
    /**
     * How many of the workspace's most recent runs are searched for
     * outputs. Bounded so the lookup stays on the `(workspace_id,
     * created_at)` and `(run_id, key)` indexes instead of scanning
     * `node_runs` by type.
     */
    private const int RECENT_RUNS = 200;

    private const int SAMPLES = 5;

    /**
     * @var array<string, array<string, array<string, mixed>>|null>
     */
    private array $cache = [];

    public function __construct(private readonly OutputSchemaInferrer $inferrer) {}

    /**
     * @return array<string, array<string, mixed>>|null The inferred schema, or null when this workspace has never seen the type produce output.
     */
    public function schemaFor(Workspace $workspace, string $type): ?array
    {
        $cacheKey = "{$workspace->id}:{$type}";

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        $samples = $this->samples($workspace, $type);

        return $this->cache[$cacheKey] = $samples === [] ? null : $this->inferrer->infer($samples);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function placeholderFor(Workspace $workspace, string $type): ?array
    {
        $schema = $this->schemaFor($workspace, $type);

        return $schema === null ? null : $this->inferrer->placeholder($schema);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function samples(Workspace $workspace, string $type): array
    {
        $pinned = WorkflowNode::query()
            ->whereHas('workflow', fn ($query) => $query->where('workspace_id', $workspace->id))
            ->where('type', $type)
            ->whereNotNull('pinned_data')
            ->latest('pinned_at')
            ->limit(self::SAMPLES)
            ->pluck('pinned_data')
            ->all();

        $remaining = self::SAMPLES - count($pinned);

        if ($remaining <= 0) {
            return $pinned;
        }

        $recentRunIds = Run::query()
            ->where('workspace_id', $workspace->id)
            ->latest()
            ->limit(self::RECENT_RUNS)
            ->pluck('id');

        $observed = NodeRun::query()
            ->whereIn('run_id', $recentRunIds)
            ->where('type', $type)
            ->where('status', NodeRunStatus::Completed)
            ->whereNotNull('output')
            ->latest('finished_at')
            ->limit($remaining)
            ->pluck('output')
            ->all();

        return array_values(array_filter([...$pinned, ...$observed], fn (mixed $output): bool => is_array($output) && $output !== []));
    }
}
