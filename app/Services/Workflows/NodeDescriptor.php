<?php

namespace App\Services\Workflows;

use App\Contracts\DeclaresOutputSchema;
use App\Contracts\HasIcon;
use App\Contracts\NodeContract;
use App\Contracts\SupportsTriggerMode;
use App\Enums\Triggers\TriggerNodeType;
use App\Models\Nodes\NodeCategory;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Services\Triggers\TriggerNodes;

/**
 * Everything the app says about one node type.
 *
 * Built once per type by NodeRegistry, from the node's class or, for a
 * flow-control type, from FlowControlNodes. describe() and the editor's
 * catalog both render from it, so they can't drift apart.
 *
 * It holds only what the code defines. Things that can change while a worker
 * runs (a category's colour, the `triggers.nodes_enabled` flag) are applied
 * when an entry is rendered.
 */
final readonly class NodeDescriptor
{
    /**
     * @param  array<string, mixed>  $configSchema  includes the trigger-mode settings when the node supports them
     * @param  array<string, mixed>|null  $outputSchema  null when the node doesn't declare one
     * @param  string|null  $icon  null to use the category's icon
     * @param  bool  $isTriggerNode  a dedicated trigger node (schedule, webhook, polling, manual)
     * @param  bool  $supportsTriggerMode  a reader that can be switched on as a flow trigger
     * @param  bool  $listed  false for a retired type that is no longer offered
     * @param  string|null  $triggerName  the reader's name in Triggers & Events
     * @param  string|null  $triggerDescription  the reader's description in Triggers & Events
     */
    public function __construct(
        public string $type,
        public string $category,
        public string $name,
        public string $description,
        public array $configSchema,
        public ?array $outputSchema = null,
        public ?string $icon = null,
        public bool $requiresConnector = false,
        public bool $isTriggerNode = false,
        public bool $supportsTriggerMode = false,
        public bool $listed = true,
        public ?string $triggerName = null,
        public ?string $triggerDescription = null,
    ) {}

    /**
     * Describe a built-in node from its class.
     */
    public static function fromNode(string $type, NodeContract $node): self
    {
        $configSchema = $node->configSchema();

        // Readers that can act as a flow trigger all get the same trigger-mode
        // settings, added here once rather than in each node's schema.
        if ($node instanceof SupportsTriggerMode) {
            $configSchema['properties'] = [
                ...($configSchema['properties'] ?? []),
                ...TriggerNodes::appTriggerFields($node->triggerPollMinutes()),
            ];
        }

        return new self(
            type: $type,
            category: $node->category(),
            name: $node->name(),
            description: $node->description(),
            configSchema: $configSchema,
            outputSchema: $node instanceof DeclaresOutputSchema ? $node->outputSchema() : null,
            icon: $node instanceof HasIcon ? $node->icon() : null,
            requiresConnector: in_array(ResolvesConnectorCredential::class, class_uses_recursive($node), true),
            isTriggerNode: TriggerNodeType::isTriggerNode($type),
            supportsTriggerMode: $node instanceof SupportsTriggerMode,
            listed: TriggerNodeType::tryFrom($type)?->isListed() ?? true,
            triggerName: $node instanceof SupportsTriggerMode ? $node->triggerName() : null,
            triggerDescription: $node instanceof SupportsTriggerMode ? $node->triggerDescription() : null,
        );
    }

    /**
     * The shape authoring needs (schema validation, the builder assistant).
     *
     * No display fields, and `output_schema` only when the node declares one.
     *
     * @return array{type: string, category: string, name: string, description: string, config_schema: array<string, mixed>, output_schema?: array<string, mixed>}
     */
    public function toDescription(): array
    {
        return [
            'type' => $this->type,
            'category' => $this->category,
            'name' => $this->name,
            'description' => $this->description,
            'config_schema' => $this->configSchema,
            ...($this->outputSchema !== null ? ['output_schema' => $this->outputSchema] : []),
        ];
    }

    /**
     * The shape the editor's node library shows.
     *
     * The icon falls back to the category's; the colour always comes from it.
     *
     * @return array{type: string, category: string, name: string, description: string, config_schema: array<string, mixed>, output_schema: array<string, mixed>|null, icon: ?string, color: ?string, requires_connector: bool, node_kind: 'trigger'|'action', supports_trigger: bool, listed: bool}
     */
    public function toCatalogEntry(?NodeCategory $category, bool $triggersEnabled): array
    {
        return [
            'type' => $this->type,
            'category' => $this->category,
            'name' => $this->name,
            'description' => $this->description,
            'config_schema' => $this->configSchema,
            'output_schema' => $this->outputSchema,
            'icon' => $this->icon ?? $category?->icon,
            'color' => $category?->color,
            'requires_connector' => $this->requiresConnector,
            'node_kind' => $this->isTriggerNode ? 'trigger' : 'action',
            'supports_trigger' => $triggersEnabled && $this->supportsTriggerMode,
            'listed' => $this->listed,
        ];
    }

    /**
     * A reader as the Triggers & Events category lists it.
     *
     * Named for its event ("Gmail: New email") and flagged `trigger_mode`, so
     * the editor adds it with "Activate as flow trigger" already on.
     *
     * @return array<string, mixed>
     */
    public function toTriggerCatalogEntry(?NodeCategory $category): array
    {
        return [
            ...$this->toCatalogEntry($category, triggersEnabled: true),
            'name' => $this->triggerName ?? $this->name,
            'description' => $this->triggerDescription ?? $this->description,
            'trigger_mode' => true,
        ];
    }
}
