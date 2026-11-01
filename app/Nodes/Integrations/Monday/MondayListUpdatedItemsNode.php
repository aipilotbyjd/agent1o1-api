<?php

namespace App\Nodes\Integrations\Monday;

class MondayListUpdatedItemsNode extends AbstractMondayItemTriggerNode
{
    public function type(): string
    {
        return 'monday_list_updated_items';
    }

    public function name(): string
    {
        return 'monday.com: List Changed Items';
    }

    public function description(): string
    {
        return 'Lists the items changed lately on a board, most recent first (an item changed twice appears as two events). As a trigger it starts the flow for each one. Instant, with a check every few minutes.';
    }

    protected function createdOnly(): bool
    {
        return false;
    }

    public function triggerName(): string
    {
        return 'monday.com: Item changed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when an item on a board is changed or renamed. Instant, with a check every few minutes.';
    }
}
