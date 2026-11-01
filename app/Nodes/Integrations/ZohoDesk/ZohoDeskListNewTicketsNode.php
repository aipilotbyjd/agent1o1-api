<?php

namespace App\Nodes\Integrations\ZohoDesk;

class ZohoDeskListNewTicketsNode extends AbstractZohoDeskTicketTriggerNode
{
    public function type(): string
    {
        return 'zoho_desk_list_new_tickets';
    }

    public function name(): string
    {
        return 'Zoho Desk: List New Tickets';
    }

    public function description(): string
    {
        return 'Lists the tickets opened lately, newest first. As a trigger it starts the flow for each one. Instant, with a check every few minutes.';
    }

    protected function stamp(): string
    {
        return 'createdTime';
    }

    public function triggerName(): string
    {
        return 'Zoho Desk: New ticket';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a ticket is opened in Zoho Desk. Instant, with a check every few minutes.';
    }
}
