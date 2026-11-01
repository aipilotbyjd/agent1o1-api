<?php

namespace App\Services\Workflows;

use App\Contracts\NodeContract;
use App\Enums\Workflows\FlowControlNodeType;
use App\Models\Nodes\CatalogNodeOverride;
use App\Models\Nodes\NodeCategory;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Looks up every node type a workflow graph can hold.
 *
 * There are three kinds of type:
 *
 * - Built-in: a NodeContract class registered at boot by
 *   NodeRegistryServiceProvider. The engine resolves it and calls execute().
 * - Flow-control (loop, join_paths, wait, human_approval, subflow): placeable
 *   and described, but never resolved, because WorkflowRunner drives them
 *   itself. Their metadata comes from FlowControlNodes.
 * - Custom (`custom:{id}`): a workspace's own node. It can be saved and
 *   published but not run yet.
 *
 * Each placeable type is described by one NodeDescriptor, built once and
 * cached for the life of this singleton. describe() and catalog() both
 * render from it, so the builder assistant and the editor always agree.
 *
 * @see docs/WORKFLOWS_PLAN.md "Node contract & registry"
 */
class NodeRegistry
{
    private const string CUSTOM_PREFIX = 'custom:';

    /**
     * Built-in node classes, keyed by type.
     *
     * @var array<string, class-string<NodeContract>>
     */
    private array $builtins = [];

    /**
     * Descriptors built so far, keyed by type.
     *
     * @var array<string, NodeDescriptor>
     */
    private array $descriptors = [];

    /**
     * Register a built-in node class under its type.
     *
     * @param  class-string<NodeContract>  $class
     */
    public function register(string $type, string $class): void
    {
        $this->builtins[$type] = $class;

        unset($this->descriptors[$type]);
    }

    /**
     * Whether resolve() will return an executable node for this type.
     *
     * Custom types answer false until custom node execution exists (Stage 11).
     * Answering true and then throwing from resolve() turned an unbuilt
     * feature into a hard error for every has()-then-resolve() caller: saving
     * a canvas, publishing, testing a node, binding an agent tool. Answering
     * false sends custom types down the path an unknown type already takes:
     * schema checks skip it, ToolRegistry drops the binding, and a run that
     * reaches one fails only that node.
     */
    public function has(string $type): bool
    {
        return ! $this->isCustom($type) && isset($this->builtins[$type]);
    }

    /**
     * Whether this is a workspace's own `custom:{id}` node.
     *
     * A graph may hold one: it saves and publishes, and fails only when a run
     * reaches it, so the rest of the graph can route around it.
     */
    public function isCustom(string $type): bool
    {
        return str_starts_with($type, self::CUSTOM_PREFIX);
    }

    /**
     * Whether this is an engine-driven type that is never resolved.
     */
    public function isFlowControl(string $type): bool
    {
        return FlowControlNodeType::tryFrom($type) !== null;
    }

    /**
     * Whether this type can be placed on a graph: a built-in or flow-control
     * node.
     *
     * Authoring code (a canvas save, the builder assistant) should ask this;
     * has() is for callers about to resolve().
     */
    public function isPlaceable(string $type): bool
    {
        return $this->has($type) || $this->isFlowControl($type);
    }

    /**
     * Build an executable instance of a built-in node.
     *
     * A fresh instance each call, unlike descriptors: a node may hold
     * per-execution state.
     *
     * @throws InvalidArgumentException for a custom or unknown type
     */
    public function resolve(string $type): NodeContract
    {
        // These messages are saved on the failing NodeRun and shown to whoever
        // is watching the run, so they're written for people, not developers.
        if ($this->isCustom($type)) {
            throw new InvalidArgumentException("Custom nodes can't be executed yet (type [{$type}]).");
        }

        if (! isset($this->builtins[$type])) {
            throw new InvalidArgumentException("No node is registered for type [{$type}].");
        }

        return app($this->builtins[$type]);
    }

    /**
     * Every built-in node class, keyed by type: what a `tool` step or an
     * agent's tool list can point at.
     *
     * @return array<string, class-string<NodeContract>>
     */
    public function connectors(): array
    {
        return $this->builtins;
    }

    /**
     * The descriptor for a placeable type, or null for any other type.
     */
    public function descriptor(string $type): ?NodeDescriptor
    {
        if ($this->has($type)) {
            return $this->descriptors[$type] ??= NodeDescriptor::fromNode($type, $this->resolve($type));
        }

        $flowControl = FlowControlNodeType::tryFrom($type);

        if ($flowControl === null) {
            return null;
        }

        return $this->descriptors[$type] ??= FlowControlNodes::descriptor($flowControl);
    }

    /**
     * The config schema for a placeable type, or null for any other type.
     *
     * @return array<string, mixed>|null
     */
    public function configSchemaFor(string $type): ?array
    {
        return $this->descriptor($type)?->configSchema;
    }

    /**
     * A placeable type's name, description, category and schemas, without the
     * display fields catalog() adds.
     *
     * @return array{type: string, category: string, name: string, description: string, config_schema: array<string, mixed>, output_schema?: array<string, mixed>}|null
     */
    public function describe(string $type): ?array
    {
        return $this->descriptor($type)?->toDescription();
    }

    /**
     * describe() for every type a new graph may use, built-ins first and then
     * flow-control.
     *
     * Retired types (the manual trigger) are left out. They stay placeable,
     * so graphs that already hold one still save.
     *
     * @return array<int, array{type: string, category: string, name: string, description: string, config_schema: array<string, mixed>, output_schema?: array<string, mixed>}>
     */
    public function placeableTypes(): array
    {
        return $this->allDescriptors()
            ->filter(fn (NodeDescriptor $descriptor): bool => $descriptor->listed)
            ->map(fn (NodeDescriptor $descriptor): array => $descriptor->toDescription())
            ->values()
            ->all();
    }

    /**
     * Every node as the editor's node library shows it, built-ins first and
     * then flow-control.
     *
     * Icon and colour come from the node's category. Retired types are
     * included with `listed` false, so saved graphs that hold one still
     * render. With `triggers.nodes_enabled` off, the trigger nodes and the
     * "Activate as flow trigger" switch are left out.
     *
     * @return array<int, array{type: string, category: string, name: string, description: string, config_schema: array<string, mixed>, output_schema: array<string, mixed>|null, icon: ?string, color: ?string, requires_connector: bool, node_kind: 'trigger'|'action', supports_trigger: bool, listed: bool}>
     */
    public function catalog(): array
    {
        $categories = $this->categories();
        $triggersEnabled = $this->triggersEnabled();

        $overrides = $this->nodeOverrides();

        return $this->allDescriptors()
            ->reject(fn (NodeDescriptor $descriptor): bool => $descriptor->isTriggerNode && ! $triggersEnabled)
            ->map(fn (NodeDescriptor $descriptor): array => $this->withOverride($descriptor->toCatalogEntry($categories->get($descriptor->category), $triggersEnabled), $overrides))
            ->values()
            ->all();
    }

    /**
     * The reader nodes that can start a flow, as the Triggers & Events
     * category lists them, sorted by name.
     *
     * Each is named for its event ("Gmail: New email") and flagged
     * `trigger_mode`, so the editor adds it with "Activate as flow trigger"
     * already on. It keeps its own type and category.
     *
     * @return array<int, array<string, mixed>>
     */
    public function triggerCatalog(): array
    {
        if (! $this->triggersEnabled()) {
            return [];
        }

        $categories = $this->categories();
        $overrides = $this->nodeOverrides();

        return $this->allDescriptors()
            ->filter(fn (NodeDescriptor $descriptor): bool => $descriptor->supportsTriggerMode && $descriptor->listed && $overrides->get($descriptor->type)?->status !== 'hidden')
            ->map(fn (NodeDescriptor $descriptor): array => $this->withOverride($descriptor->toTriggerCatalogEntry($categories->get($descriptor->category)), $overrides))
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * Descriptors for every placeable type, built-ins first and then
     * flow-control.
     *
     * @return Collection<int, NodeDescriptor>
     */
    private function allDescriptors(): Collection
    {
        $types = [
            ...array_keys($this->builtins),
            ...array_map(fn (FlowControlNodeType $case): string => $case->value, FlowControlNodeType::cases()),
        ];

        return collect($types)->map(fn (string $type): NodeDescriptor => $this->descriptor($type));
    }

    /**
     * Node categories keyed by slug.
     *
     * Queried on each call rather than cached: a category's colour or icon
     * can change while a long-running worker holds this singleton.
     *
     * @return Collection<string, NodeCategory>
     */
    private function categories(): Collection
    {
        return NodeCategory::query()->get()->keyBy('slug');
    }

    /**
     * What an admin flagged on individual nodes. Read on every call, not kept,
     * because a queue worker holds this registry for a long time.
     *
     * @return Collection<string, CatalogNodeOverride>
     */
    private function nodeOverrides(): Collection
    {
        return CatalogNodeOverride::query()->get()->keyBy('node_type');
    }

    /**
     * Adds an admin's flag to a catalog entry: `status` (beta, deprecated or
     * hidden) and its note. A hidden node is no longer offered, but graphs that
     * already hold one still resolve it, as with a retired type.
     *
     * @param  array<string, mixed>  $entry
     * @param  Collection<string, CatalogNodeOverride>  $overrides
     * @return array<string, mixed>
     */
    private function withOverride(array $entry, Collection $overrides): array
    {
        $override = $overrides->get($entry['type']);

        return [
            ...$entry,
            'status' => $override?->status,
            'status_note' => $override?->note,
            'listed' => $entry['listed'] && $override?->status !== 'hidden',
        ];
    }

    /**
     * Whether trigger nodes are rolled out (`triggers.nodes_enabled`).
     */
    private function triggersEnabled(): bool
    {
        return (bool) config('triggers.nodes_enabled');
    }
}
