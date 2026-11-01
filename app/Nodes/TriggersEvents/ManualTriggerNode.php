<?php

namespace App\Nodes\TriggersEvents;

use App\Enums\Triggers\TriggerNodeType;

/**
 * Retired: marks where a manual run starts.
 *
 * No longer offered (TriggerNodeType::isListed()), because the Run button
 * already starts a workflow at its first steps. Kept so workflows that hold
 * one still save and run.
 */
class ManualTriggerNode extends TriggerNode
{
    /**
     * Which trigger node this is.
     */
    public function nodeType(): TriggerNodeType
    {
        return TriggerNodeType::Manual;
    }

    /**
     * The name shown in the node library.
     */
    public function name(): string
    {
        return 'Manual Trigger';
    }

    /**
     * The Hugeicons name shown on the node.
     */
    public function icon(): string
    {
        return 'play';
    }

    /**
     * One line on what the node does.
     */
    public function description(): string
    {
        return 'Starts the workflow when someone clicks Run or calls the run API.';
    }

    /**
     * The node's settings.
     */
    public function configSchema(): array
    {
        return ['type' => 'object', 'properties' => []];
    }
}
