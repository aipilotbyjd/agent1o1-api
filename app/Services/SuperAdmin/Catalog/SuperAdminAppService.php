<?php

namespace App\Services\SuperAdmin\Catalog;

use App\Models\Connectors\Connector;
use App\Models\Nodes\CatalogNodeOverride;
use App\Models\Nodes\NodeCategory;
use App\Models\Runs\NodeRun;
use App\Models\SuperAdmin\SuperAdmin;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use App\Services\Workflows\CatalogTaxonomy;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One integration as an admin sees it: its place in the editor's Apps panel
 * (the node category), its connector (on or off, featured), who uses it, and
 * its nodes. Every change is audited with a reason, and an app carries a
 * history that can be rolled back one step at a time.
 */
class SuperAdminAppService
{
    /** Fields that live on the category. */
    private const CATEGORY_FIELDS = ['name', 'description', 'icon', 'color', 'sort_order', 'functions', 'vendor', 'status', 'status_note'];

    /** Fields that live on the connector. */
    private const CONNECTOR_FIELDS = ['is_featured', 'is_active'];

    /** Shown on both rows, so a change is written to both. */
    private const MIRRORED_FIELDS = ['name', 'description', 'icon', 'color', 'sort_order'];

    /** What the seeders write, and so what an edit must protect from the next re-seed. */
    private const SEEDED_CATEGORY_FIELDS = ['name', 'description', 'icon', 'color', 'sort_order', 'functions', 'vendor'];

    private const SEEDED_CONNECTOR_FIELDS = ['name', 'description', 'icon', 'color', 'sort_order', 'is_featured'];

    public function __construct(
        private readonly SuperAdminAuditLogger $audit,
        private readonly CatalogTaxonomy $taxonomy,
        private readonly NodeRegistry $registry,
    ) {}

    /**
     * @param  array{q?: ?string, function?: ?string, vendor?: ?string, state?: ?string, status?: ?string}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function list(array $filters = []): Collection
    {
        $term = trim((string) ($filters['q'] ?? ''));

        $categories = NodeCategory::query()
            ->where('kind', 'app')
            ->when($term !== '', fn ($query) => $query->where(fn ($inner) => $inner->whereLike('name', "%{$term}%")->orWhereLike('slug', "%{$term}%")))
            ->when(! empty($filters['vendor']), fn ($query) => $query->where('vendor', $filters['vendor']))
            ->when(! empty($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $connectors = Connector::query()->get()->keyBy('key');
        $usage = $this->usage();
        $nodeCounts = collect($this->registry->catalog())->where('listed', true)->countBy('category');

        return $categories
            ->map(fn (NodeCategory $category): array => $this->row($category, $connectors->get($category->slug), $usage, (int) $nodeCounts->get($category->slug, 0)))
            ->when(! empty($filters['function']), fn (Collection $rows) => $rows->filter(fn (array $row): bool => in_array($filters['function'], $row['functions'], true)))
            ->when(in_array($filters['state'] ?? null, ['on', 'off'], true), fn (Collection $rows) => $rows->filter(fn (array $row): bool => $row['connector'] !== null && $row['connector']['is_active'] === ($filters['state'] === 'on')))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function show(NodeCategory $category): array
    {
        $connector = Connector::query()->where('key', $category->slug)->first();
        $nodeCount = collect($this->registry->catalog())->where('category', $category->slug)->where('listed', true)->count();

        return $this->row($category, $connector, $this->usage(), $nodeCount);
    }

    /**
     * Changes whatever is in `$changes` and leaves the rest. Nothing is written
     * (and nothing is audited) when nothing differs.
     *
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed> what changed, by field; empty when nothing did
     */
    public function update(SuperAdmin $actor, NodeCategory $category, array $changes, string $reason, string $action = 'catalog.app.updated'): array
    {
        $connector = Connector::query()->where('key', $category->slug)->first();

        abort_if(
            $connector === null && array_intersect_key($changes, array_flip(self::CONNECTOR_FIELDS)) !== [],
            409,
            'This app has no connector, so it cannot be switched on or off or featured.',
        );

        $before = $after = [];
        $categoryEdited = $connectorEdited = [];

        foreach ($changes as $field => $value) {
            if (in_array($field, self::CATEGORY_FIELDS, true)) {
                $value = $field === 'functions' ? array_values($value) : $value;

                if ($category->{$field} === $value) {
                    continue;
                }

                $before[$field] = $category->{$field};
                $after[$field] = $value;
                $category->{$field} = $value;

                if (in_array($field, self::SEEDED_CATEGORY_FIELDS, true)) {
                    $categoryEdited[] = $field;
                }

                if ($connector !== null && in_array($field, self::MIRRORED_FIELDS, true)) {
                    $connector->{$field} = $value;

                    if (in_array($field, self::SEEDED_CONNECTOR_FIELDS, true)) {
                        $connectorEdited[] = $field;
                    }
                }
            } elseif (in_array($field, self::CONNECTOR_FIELDS, true) && $connector !== null) {
                if ($connector->{$field} === $value) {
                    continue;
                }

                $before[$field] = $connector->{$field};
                $after[$field] = $value;
                $connector->{$field} = $value;

                if (in_array($field, self::SEEDED_CONNECTOR_FIELDS, true)) {
                    $connectorEdited[] = $field;
                }
            }
        }

        if ($before === []) {
            return [];
        }

        // An app that is operational has nothing to explain.
        if (($after['status'] ?? null) === 'operational' && ! array_key_exists('status_note', $changes) && $category->status_note !== null) {
            $before['status_note'] = $category->status_note;
            $after['status_note'] = null;
            $category->status_note = null;
        }

        DB::transaction(function () use ($actor, $category, $connector, $categoryEdited, $connectorEdited, $before, $after, $reason, $action): void {
            $category->markAdminEdited($categoryEdited);
            $category->save();

            if ($connector !== null && $connector->isDirty()) {
                $connector->markAdminEdited($connectorEdited);
                $connector->save();
            }

            $this->audit->record($actor, $action, $category, $before, $after, $reason);
        });

        $this->taxonomy->flush();

        return $after;
    }

    /**
     * Moves an app one place up or down the Apps panel's order by swapping
     * places with its neighbour.
     */
    public function move(SuperAdmin $actor, NodeCategory $category, string $direction, string $reason): void
    {
        $ordered = NodeCategory::query()->where('kind', 'app')->orderBy('sort_order')->orderBy('name')->get()->values();
        $index = $ordered->search(fn (NodeCategory $candidate): bool => $candidate->is($category));
        $neighbour = $ordered->get($direction === 'up' ? $index - 1 : $index + 1);

        abort_if($neighbour === null, 409, $direction === 'up' ? 'It is already first.' : 'It is already last.');

        if ($category->sort_order === $neighbour->sort_order) {
            // Equal places cannot be swapped, so step past the neighbour instead.
            $this->update($actor, $category, ['sort_order' => max(0, $neighbour->sort_order + ($direction === 'up' ? -1 : 1))], $reason, 'catalog.app.moved');

            return;
        }

        $mine = $category->sort_order;
        $theirs = $neighbour->sort_order;

        $this->update($actor, $neighbour, ['sort_order' => $mine], $reason, 'catalog.app.moved');
        $this->update($actor, $category, ['sort_order' => $theirs], $reason, 'catalog.app.moved');
    }

    /**
     * What switching an app off, or changing it, would touch.
     *
     * @return array{workspaces: int, credentials: int, failing_credentials: int, workflows: int, runs_30d: int, node_types: int}
     */
    public function impact(NodeCategory $category): array
    {
        $types = $this->nodeTypes($category);
        $connector = Connector::query()->where('key', $category->slug)->first();

        $credentials = $connector === null
            ? ['workspaces' => 0, 'credentials' => 0, 'failing' => 0]
            : (array) DB::table('connector_credentials')
                ->where('connector_id', $connector->id)
                ->whereNull('deleted_at')
                ->selectRaw('count(distinct workspace_id) as workspaces, count(*) as credentials, coalesce(sum(case when last_test_ok = ? then 1 else 0 end), 0) as failing', [false])
                ->first();

        return [
            'workspaces' => (int) $credentials['workspaces'],
            'credentials' => (int) $credentials['credentials'],
            'failing_credentials' => (int) $credentials['failing'],
            'workflows' => $types === [] ? 0 : (int) DB::table('workflow_nodes')
                ->join('workflows', 'workflows.id', '=', 'workflow_nodes.workflow_id')
                ->whereNull('workflows.deleted_at')
                ->whereIn('workflow_nodes.type', $types)
                ->distinct()
                ->count('workflow_nodes.workflow_id'),
            'runs_30d' => $types === [] ? 0 : NodeRun::query()->whereIn('type', $types)->where('created_at', '>=', now()->subDays(30))->count(),
            'node_types' => count($types),
        ];
    }

    /**
     * The latest changes to an app, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function history(NodeCategory $category, int $limit = 20): array
    {
        return SuperAdminAuditLog::query()
            ->with('admin:id,name,email')
            ->where('subject_type', 'node_category')
            ->where('subject_id', $category->id)
            ->where('action', 'like', 'catalog.app%')
            ->latest('created_at')
            ->latest('sequence')
            ->limit($limit)
            ->get()
            ->map(fn (SuperAdminAuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'before' => $log->before,
                'after' => $log->after,
                'reason' => $log->reason,
                'actor' => $log->admin?->name ?? $log->actor_label,
                'created_at' => $log->created_at,
            ])
            ->all();
    }

    /**
     * Puts an app back as it was before one logged change.
     */
    public function revert(SuperAdmin $actor, NodeCategory $category, SuperAdminAuditLog $log, string $reason): array
    {
        abort_unless(
            $log->subject_type === 'node_category' && $log->subject_id === $category->id && str_starts_with($log->action, 'catalog.app') && ! str_ends_with($log->action, '.reverted'),
            404,
            'That change does not belong to this app.',
        );

        $restore = array_intersect_key($log->before ?? [], array_flip([...self::CATEGORY_FIELDS, ...self::CONNECTOR_FIELDS]));

        abort_if($restore === [], 409, 'There is nothing to put back for that change.');

        return $this->update($actor, $category, $restore, $reason, 'catalog.app.reverted');
    }

    /**
     * One change applied to many apps under one reason.
     *
     * @param  list<string>  $ids
     * @return array{updated: int, unchanged: int, skipped: list<string>}
     */
    public function bulk(SuperAdmin $actor, array $ids, string $action, mixed $value, string $reason): array
    {
        $changes = match ($action) {
            'set_vendor' => ['vendor' => $value],
            'set_functions' => ['functions' => $value],
            'set_featured' => ['is_featured' => (bool) $value],
            'switch_on' => ['is_active' => true],
            'switch_off' => ['is_active' => false],
        };

        $updated = $unchanged = 0;
        $skipped = [];

        $slugsWithConnector = Connector::query()->pluck('key')->all();

        foreach (NodeCategory::query()->where('kind', 'app')->whereIn('id', $ids)->get() as $category) {
            if (! in_array($category->slug, $slugsWithConnector, true) && array_intersect_key($changes, array_flip(self::CONNECTOR_FIELDS)) !== []) {
                $skipped[] = $category->slug;

                continue;
            }

            $this->update($actor, $category, $changes, $reason) === [] ? $unchanged++ : $updated++;
        }

        return ['updated' => $updated, 'unchanged' => $unchanged, 'skipped' => $skipped];
    }

    /**
     * An app's nodes with any flag an admin put on them.
     *
     * @return list<array<string, mixed>>
     */
    public function nodes(NodeCategory $category): array
    {
        return collect($this->registry->catalog())
            ->where('category', $category->slug)
            ->map(fn (array $node): array => [
                'type' => $node['type'],
                'name' => $node['name'],
                'kind' => $node['node_kind'],
                'can_start_flow' => $node['supports_trigger'] || $node['node_kind'] === 'trigger',
                'status' => $node['status'],
                'note' => $node['status_note'],
            ])
            ->values()
            ->all();
    }

    /**
     * Flags one node as beta, deprecated or hidden, or clears the flag with a null status.
     */
    public function setNodeStatus(SuperAdmin $actor, NodeCategory $category, string $type, ?string $status, ?string $note, string $reason): array
    {
        abort_unless(in_array($type, $this->nodeTypes($category), true), 404, 'That node does not belong to this app.');

        $override = CatalogNodeOverride::query()->where('node_type', $type)->first();
        $before = ['type' => $type, 'status' => $override?->status, 'note' => $override?->note];

        if ($status === null) {
            $override?->delete();
            $note = null;
        } else {
            CatalogNodeOverride::query()->updateOrCreate(['node_type' => $type], ['status' => $status, 'note' => $note]);
        }

        $after = ['type' => $type, 'status' => $status, 'note' => $note];

        if ($before !== $after) {
            $this->audit->record($actor, 'catalog.node.status_changed', $category, $before, $after, $reason);
        }

        return $after;
    }

    /**
     * @return list<string>
     */
    private function nodeTypes(NodeCategory $category): array
    {
        return collect($this->registry->catalog())->where('category', $category->slug)->pluck('type')->values()->all();
    }

    /**
     * What each connector's credentials look like, keyed by connector id.
     *
     * @return Collection<string, object>
     */
    private function usage(): Collection
    {
        return DB::table('connector_credentials')
            ->whereNull('deleted_at')
            ->groupBy('connector_id')
            ->selectRaw('connector_id, count(*) as credentials, count(distinct workspace_id) as workspaces, coalesce(sum(case when last_test_ok = ? then 1 else 0 end), 0) as failing', [false])
            ->get()
            ->keyBy('connector_id');
    }

    /**
     * @param  Collection<string, object>  $usage
     * @return array<string, mixed>
     */
    private function row(NodeCategory $category, ?Connector $connector, Collection $usage, int $nodeCount): array
    {
        $used = $connector === null ? null : $usage->get($connector->id);

        return [
            'id' => $category->id,
            'slug' => $category->slug,
            'name' => $category->name,
            'description' => $category->description,
            'icon' => $category->icon,
            'color' => $category->color,
            'sort_order' => $category->sort_order,
            'functions' => array_values($category->functions ?? []),
            'vendor' => $category->vendor,
            'status' => $category->status,
            'status_note' => $category->status_note,
            'nodes_count' => $nodeCount,
            'edited_fields' => array_values(array_unique([...($category->admin_edited_fields ?? []), ...($connector?->admin_edited_fields ?? [])])),
            'connector' => $connector === null ? null : [
                'id' => $connector->id,
                'is_active' => $connector->is_active,
                'is_featured' => $connector->is_featured,
            ],
            'usage' => [
                'workspaces' => (int) ($used->workspaces ?? 0),
                'credentials' => (int) ($used->credentials ?? 0),
                'failing_credentials' => (int) ($used->failing ?? 0),
            ],
        ];
    }
}
