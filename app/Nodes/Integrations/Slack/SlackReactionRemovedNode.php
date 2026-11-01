<?php

namespace App\Nodes\Integrations\Slack;

use App\Contracts\Triggers\NeedsPushDelivery;
use App\Models\Runs\Run;

/**
 * A reaction someone took off a message. Slack never lists removals, so this
 * can only start a flow through the Events API (instant delivery); reading it
 * returns nothing.
 *
 * Each removal is its own event: the same person can add and remove the same
 * reaction again, so the item is named by when it happened too.
 */
class SlackReactionRemovedNode extends SlackReactionAddedNode implements NeedsPushDelivery
{
    public function type(): string
    {
        return 'slack_reaction_removed';
    }

    public function name(): string
    {
        return 'Slack: Reaction Removed';
    }

    public function description(): string
    {
        return 'Starts a flow when someone removes a reaction from a message in a Slack channel. Works only with instant delivery: Slack does not list removed reactions.';
    }

    /**
     * Nothing to read: removals are only ever pushed.
     */
    public function execute(Run $run, array $config, array $context): array
    {
        return ['reactions' => []];
    }

    public function triggerName(): string
    {
        return 'Slack: Reaction removed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when someone removes a reaction from a message in the channel. Needs instant delivery.';
    }

    /**
     * Message, emoji, person and the moment it was removed.
     */
    public function triggerItemId(array $item): string
    {
        return parent::triggerItemId($item).':removed:'.($item['event_ts'] ?? '');
    }
}
