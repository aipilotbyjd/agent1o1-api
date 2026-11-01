<?php

namespace App\Nodes\Integrations\Monday;

class MondayListNewItemsNode extends AbstractMondayItemTriggerNode
{
    public function type(): string
    {
        return 'monday_list_new_items';
    }

    public function name(): string
    {
        return 'monday.com: List New Items';
    }

    public function description(): string
    {
        return 'Lists the items added lately to a board, newest first. As a trigger it starts the flow for each one. Instant, with a check every few minutes.';
    }

    protected function createdOnly(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'monday.com: New item';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when an item is added to a board. Instant, with a check every few minutes.';
    }
}
