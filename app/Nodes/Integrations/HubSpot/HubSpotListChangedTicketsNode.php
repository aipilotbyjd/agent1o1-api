<?php

namespace App\Nodes\Integrations\HubSpot;

class HubSpotListChangedTicketsNode extends AbstractHubSpotRecordTriggerNode
{
    public function type(): string
    {
        return 'hubspot_list_changed_tickets';
    }

    public function name(): string
    {
        return 'HubSpot: List Changed Tickets';
    }

    public function description(): string
    {
        return 'Lists the tickets changed lately, most recent first (a ticket changed twice appears as two events). As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function object(): string
    {
        return 'tickets';
    }

    protected function createdOnly(): bool
    {
        return false;
    }

    public function triggerName(): string
    {
        return 'HubSpot: Ticket changed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a ticket is changed in HubSpot, or created. Checks every few minutes.';
    }
}
