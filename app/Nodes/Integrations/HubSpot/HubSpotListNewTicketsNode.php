<?php

namespace App\Nodes\Integrations\HubSpot;

class HubSpotListNewTicketsNode extends AbstractHubSpotRecordTriggerNode
{
    public function type(): string
    {
        return 'hubspot_list_new_tickets';
    }

    public function name(): string
    {
        return 'HubSpot: List New Tickets';
    }

    public function description(): string
    {
        return 'Lists the tickets created lately, newest first. As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function object(): string
    {
        return 'tickets';
    }

    protected function createdOnly(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'HubSpot: New ticket';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a ticket is created in HubSpot. Checks every few minutes.';
    }
}
