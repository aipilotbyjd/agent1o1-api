<?php

namespace App\Nodes\Integrations\ZohoDesk;

class ZohoDeskListChangedTicketsNode extends AbstractZohoDeskTicketTriggerNode
{
    public function type(): string
    {
        return 'zoho_desk_list_changed_tickets';
    }

    public function name(): string
    {
        return 'Zoho Desk: List Changed Tickets';
    }

    public function description(): string
    {
        return 'Lists the tickets changed lately, newest first. As a trigger it starts the flow each time one changes. Instant, with a check every few minutes.';
    }

    protected function stamp(): string
    {
        return 'modifiedTime';
    }

    public function triggerName(): string
    {
        return 'Zoho Desk: Ticket updated';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a ticket is changed in Zoho Desk. Instant, with a check every few minutes.';
    }
}
