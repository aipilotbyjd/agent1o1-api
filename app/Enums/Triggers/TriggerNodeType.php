<?php

namespace App\Enums\Triggers;

/**
 * The dedicated trigger nodes in the Triggers & Events category.
 *
 * Publishing a workflow turns each schedule, webhook and polling node into a
 * `triggers` row (TriggerNodeSync), so the scheduler and the webhook router
 * query an indexed table instead of reading node JSON.
 *
 * Reader nodes that can also start a flow (Gmail, Slack...) aren't listed
 * here: see SupportsTriggerMode.
 */
enum TriggerNodeType: string
{
    /**
     * Retired: a marker for where a manual run starts. See isListed().
     */
    case Manual = 'manual_trigger';

    case Schedule = 'schedule_trigger';

    case Webhook = 'webhook_trigger';

    case Polling = 'polling_trigger';

    /**
     * Whether a `workflow_nodes.type` is one of these nodes.
     */
    public static function isTriggerNode(string $type): bool
    {
        return self::tryFrom($type) !== null;
    }

    /**
     * The `triggers.type` this node is published as.
     */
    public function triggerType(): TriggerType
    {
        return match ($this) {
            self::Manual => TriggerType::Manual,
            self::Schedule => TriggerType::Schedule,
            self::Webhook => TriggerType::Webhook,
            self::Polling => TriggerType::Polling,
        };
    }

    /**
     * Whether the editor and the builder assistant offer this node for new
     * workflows.
     *
     * The manual trigger isn't offered: as in Gumloop, clicking Run already
     * starts a workflow at its first steps, so the node only duplicated the
     * Run button. (n8n has one because its engine can't start without a
     * trigger node.) It stays registered so workflows that already hold one
     * still save and run.
     */
    public function isListed(): bool
    {
        return $this !== self::Manual;
    }

    /**
     * Whether publishing this node creates a `triggers` row.
     */
    public function materializes(): bool
    {
        return $this !== self::Manual;
    }
}
